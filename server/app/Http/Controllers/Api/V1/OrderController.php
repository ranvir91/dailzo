<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CouponService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const WITH = ['items.product', 'coupon'];

    public function __construct(private readonly CouponService $coupons) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'addressId' => ['sometimes', 'nullable', 'string'],
            'paymentMethod' => ['required', 'string'],
            // Kept for backward compatibility with older app builds; no
            // longer trusted for the order total (see below) — a client
            // could otherwise send any total it likes, coupon rules or not.
            'total' => ['required', 'numeric'],
            'couponCode' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $settings = StoreSetting::current();

        $prices = Product::whereIn('id', collect($data['items'])->pluck('productId'))
            ->get()
            ->mapWithKeys(fn (Product $p) => [
                $p->id => (float) ($p->discounted_price ?? $p->price),
            ]);

        $subtotal = collect($data['items'])
            ->sum(fn (array $item) => ($prices[$item['productId']] ?? 0) * $item['quantity']);

        if ($settings->min_order_value_enabled && $subtotal < (float) $settings->min_order_value) {
            abort(400, "Minimum order value is ₹{$settings->min_order_value}. Please add more items to your cart.");
        }

        $order = DB::transaction(function () use ($request, $data, $prices, $subtotal, $settings) {
            ['coupon' => $coupon, 'discount' => $discount] = $this->coupons->resolveForCheckout(
                $data['couponCode'] ?? null,
                $request->user(),
                $subtotal,
            );

            $total = max(0, $subtotal + (float) $settings->delivery_charges - $discount);

            $order = $request->user()->orders()->create([
                'status' => 'PENDING',
                'payment_method' => $data['paymentMethod'],
                'total' => $total,
                'coupon_id' => $coupon?->id,
                'discount_amount' => $discount,
                'delivery_charges' => (float) $settings->delivery_charges,
                'address_id' => $data['addressId'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['productId'],
                    'quantity' => $item['quantity'],
                    'price' => $prices[$item['productId']] ?? 0,
                ]);
            }

            if ($coupon) {
                $coupon->usages()->create([
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                ]);
            }

            return $order;
        });

        return ApiResponse::success($order->load(self::WITH), 'Order created successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(self::WITH)->orderByDesc('created_at');

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return ApiResponse::success($query->get(), 'Orders fetched successfully');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->find($request, $id);

        return ApiResponse::success(
            $order,
            $order ? 'Order fetched successfully' : 'Order not found',
        );
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = $this->find($request, $id);
        if (! $order) {
            return ApiResponse::success(null, 'Order not found');
        }

        if (in_array($order->status, ['DELIVERED', 'CANCELLED'], true)) {
            return ApiResponse::success($order, 'Order cannot be cancelled in its current state');
        }

        $order->update(['status' => 'CANCELLED']);

        return ApiResponse::success($order->load(self::WITH), 'Order cancelled successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);
        if (! $order) {
            return ApiResponse::success(null, 'Order not found');
        }

        $data = $request->validate(['status' => ['required', 'string']]);

        if (! $order->canTransitionTo($data['status'])) {
            return ApiResponse::success($order->load(self::WITH), 'Invalid order status transition');
        }

        $order->update(['status' => $data['status']]);

        return ApiResponse::success($order->load(self::WITH), 'Order status updated successfully');
    }

    private function find(Request $request, string $id): ?Order
    {
        $query = Order::with(self::WITH)->whereKey($id);

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->first();
    }
}
