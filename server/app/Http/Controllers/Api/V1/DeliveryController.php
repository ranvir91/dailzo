<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Services\DeliveryAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryAssignmentService $assignmentService) {}

    public function partners(): JsonResponse
    {
        return ApiResponse::success(
            DeliveryPartner::orderBy('name')->get(),
            'Delivery partners fetched successfully',
        );
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'orderId' => ['required', 'string', 'exists:orders,id'],
            'deliveryPartnerId' => ['required', 'string', 'exists:delivery_partners,id'],
        ]);

        $order = Order::findOrFail($data['orderId']);
        $partner = DeliveryPartner::findOrFail($data['deliveryPartnerId']);
        $assignment = $this->assignmentService->assign($order, $partner);

        return ApiResponse::success([
            'assignedPartnerId' => $assignment->delivery_partner_id,
            'status' => $assignment->status,
        ], 'Delivery partner assigned successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string']]);
        $status = $data['status'] ?? 'OUT_FOR_DELIVERY';

        DeliveryAssignment::whereKey($id)->update(['status' => $status]);

        return ApiResponse::success(['id' => $id, 'status' => $status], 'Delivery status updated successfully');
    }
}
