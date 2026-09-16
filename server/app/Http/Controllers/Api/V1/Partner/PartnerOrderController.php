<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\OrderComment;
use App\Services\OtpService;
use App\Support\ApiResponse;
use App\Support\IstDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartnerOrderController extends Controller
{
    private const WITH = ['order.user', 'order.address', 'order.items.product', 'order.comments.deliveryPartner'];

    public function __construct(private readonly OtpService $otp)
    {
    }

    /**
     * Forward-only: a partner can hand an order off (OUT_FOR_DELIVERY) then
     * close it out (DELIVERED), or close it out directly. Once DELIVERED (or
     * REASSIGNED away) it's no longer theirs to update.
     */
    private const ALLOWED_TRANSITIONS = [
        DeliveryAssignment::STATUS_ASSIGNED => [DeliveryAssignment::STATUS_OUT_FOR_DELIVERY, DeliveryAssignment::STATUS_DELIVERED],
        DeliveryAssignment::STATUS_OUT_FOR_DELIVERY => [DeliveryAssignment::STATUS_DELIVERED],
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in(['ALL', 'MY_ORDERS', 'PENDING', 'COMPLETED', 'OUT_FOR_DELIVERY'])],
        ]);

        [$dayStart, $dayEnd] = IstDate::dayRange($data['date'] ?? now('Asia/Kolkata')->format('Y-m-d'));
        $status = $data['status'] ?? 'ALL';

        $query = DeliveryAssignment::with(self::WITH)
            ->where('delivery_partner_id', $request->user()->id)
            ->whereBetween('created_at', [$dayStart, $dayEnd]);

        match ($status) {
            'MY_ORDERS' => $query->where('status', '!=', DeliveryAssignment::STATUS_REASSIGNED),
            'PENDING' => $query->where('status', DeliveryAssignment::STATUS_ASSIGNED),
            'COMPLETED' => $query->where('status', DeliveryAssignment::STATUS_DELIVERED),
            'OUT_FOR_DELIVERY' => $query->where('status', DeliveryAssignment::STATUS_OUT_FOR_DELIVERY),
            default => null, // ALL — every assignment for this partner that day, including history
        };

        $orders = $query->latest()->get()->map(fn (DeliveryAssignment $a) => $this->present($a));

        return ApiResponse::success($orders, 'Orders fetched successfully');
    }

    public function updateStatus(Request $request, string $orderId): JsonResponse
    {
        // `otp` is validated by hand further down, only once we know this status
        // change would actually go through — see the comment there for why.
        $data = $request->validate([
            'status' => ['required', Rule::in(['COMPLETED', 'OUT_FOR_DELIVERY'])],
            'comment' => ['sometimes', 'nullable', 'string'],
        ]);

        [$order, $assignment] = $this->findOwnedOrder($request, $orderId);
        if (! $order) {
            return ApiResponse::error('Order not found', ['Order not found or not assigned to you'], 404);
        }

        $targetAssignmentStatus = $data['status'] === 'COMPLETED'
            ? DeliveryAssignment::STATUS_DELIVERED
            : DeliveryAssignment::STATUS_OUT_FOR_DELIVERY;

        if (! in_array($targetAssignmentStatus, self::ALLOWED_TRANSITIONS[$assignment->status] ?? [], true)) {
            return ApiResponse::error(
                "Order cannot move from {$assignment->status} to {$targetAssignmentStatus}",
                ['Invalid status transition'],
                400,
            );
        }

        $customerPhone = $order->user?->phone;

        // Completing an order proves the customer actually received it: the OTP
        // generated below when the order went OUT_FOR_DELIVERY (SMS'd to them —
        // or just returned in the response while OTP_DEBUG is on) has to be read
        // back to the partner and entered here. If the partner skips straight
        // from ASSIGNED to COMPLETED, no OTP was ever generated, so this
        // correctly fails and pushes them through OUT_FOR_DELIVERY first.
        //
        // Checked only now (after ownership/transition are already confirmed
        // valid) so a request for an order that isn't the caller's, or an
        // invalid transition, still fails with 404/400 rather than a
        // validation error about a missing `otp` that was never the real
        // problem.
        if ($targetAssignmentStatus === DeliveryAssignment::STATUS_DELIVERED) {
            $otp = (string) $request->input('otp', '');
            if ($otp === '' || ! $customerPhone || ! $this->otp->verify($customerPhone, $otp)) {
                return ApiResponse::error(
                    'Invalid or expired delivery OTP',
                    ['The OTP entered does not match the one sent to the customer'],
                    400,
                );
            }
        }

        DB::transaction(function () use ($order, $assignment, $targetAssignmentStatus, $data, $request) {
            $assignment->markStatus($targetAssignmentStatus);

            // Terminal Order states (CANCELLED/DELIVERED) are left alone; otherwise
            // the partner is authoritative for the delivery-stage status.
            if (! in_array($order->status, ['CANCELLED', 'DELIVERED'], true)) {
                $order->update(['status' => $targetAssignmentStatus]);
            }

            OrderComment::create([
                'order_id' => $order->id,
                'delivery_partner_id' => $request->user()->id,
                'type' => OrderComment::TYPE_STATUS_CHANGE,
                'body' => trim("Marked as {$targetAssignmentStatus}. ".($data['comment'] ?? '')),
            ]);
        });

        $response = $this->present($assignment->fresh(self::WITH));

        if ($targetAssignmentStatus === DeliveryAssignment::STATUS_OUT_FOR_DELIVERY && $customerPhone) {
            $otp = $this->otp->generate($customerPhone);
            // No SMS gateway yet (same limitation as customer login OTP) — while
            // OTP_DEBUG is on, hand it straight back so the partner app can show
            // it during development instead of needing server log access.
            if (config('dailzo.otp_debug')) {
                $response['deliveryOtp'] = $otp;
            }
        }

        return ApiResponse::success($response, 'Order status updated successfully');
    }

    public function addComment(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);

        [$order] = $this->findOwnedOrder($request, $orderId);
        if (! $order) {
            return ApiResponse::error('Order not found', ['Order not found or not assigned to you'], 404);
        }

        $comment = OrderComment::create([
            'order_id' => $order->id,
            'delivery_partner_id' => $request->user()->id,
            'type' => OrderComment::TYPE_INCIDENT,
            'body' => $data['comment'],
        ]);

        return ApiResponse::success($comment, 'Comment added successfully');
    }

    public function reassign(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'target_partner_id' => ['required', 'string', 'exists:delivery_partners,id'],
            'reason' => ['required', 'string'],
        ]);

        /** @var DeliveryPartner $partner */
        $partner = $request->user();

        if ($data['target_partner_id'] === $partner->id) {
            return ApiResponse::error('Cannot reassign an order to yourself', ['Invalid target partner'], 400);
        }

        [$order, $assignment] = $this->findOwnedOrder($request, $orderId);
        if (! $order) {
            return ApiResponse::error('Order not found', ['Order not found or not assigned to you'], 404);
        }

        $target = DeliveryPartner::active()->find($data['target_partner_id']);
        if (! $target) {
            return ApiResponse::error('Target delivery partner is not available', ['Target partner inactive or not found'], 400);
        }

        $newAssignment = DB::transaction(function () use ($order, $assignment, $partner, $target, $data) {
            $assignment->markStatus(DeliveryAssignment::STATUS_REASSIGNED);

            $new = $order->deliveries()->create([
                'delivery_partner_id' => $target->id,
                'status' => DeliveryAssignment::STATUS_ASSIGNED,
                'status_changed_at' => now(),
            ]);

            OrderComment::create([
                'order_id' => $order->id,
                'delivery_partner_id' => $partner->id,
                'type' => OrderComment::TYPE_REASSIGNMENT,
                'body' => "Reassigned to {$target->name}: {$data['reason']}",
            ]);

            return $new;
        });

        return ApiResponse::success($this->present($newAssignment->load(self::WITH)), 'Order reassigned successfully');
    }

    /**
     * @return array{0: ?Order, 1: ?DeliveryAssignment} the order and the requesting
     * partner's active assignment on it, or [null, null] if either doesn't exist.
     */
    private function findOwnedOrder(Request $request, string $orderId): array
    {
        $order = Order::find($orderId);
        if (! $order) {
            return [null, null];
        }

        $assignment = $request->user()->activeAssignmentFor($order);
        if (! $assignment) {
            return [null, null];
        }

        return [$order, $assignment];
    }

    private function present(DeliveryAssignment $assignment): array
    {
        $order = $assignment->order;
        $data = $order->toArray();

        $data['deliveryStatus'] = $assignment->status;
        $data['statusChangedAt'] = $assignment->status_changed_at?->toIso8601String();
        $data['deliveryNotes'] = $order->comments;

        return $data;
    }
}
