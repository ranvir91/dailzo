<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const WITH = ['items.product'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'addressId' => ['sometimes', 'nullable', 'string'],
            'paymentMethod' => ['required', 'string'],
            'total' => ['required', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $settings = StoreSetting::current();
        if ($settings->min_order_value_enabled && $data['total'] < (float) $settings->min_order_value) {
            abort(400, "Minimum order value is ₹{$settings->min_order_value}. Please add more items to your cart.");
        }

        $prices = Product::whereIn('id', collect($data['items'])->pluck('productId'))
            ->get()
            ->mapWithKeys(fn (Product $p) => [
                $p->id => (float) ($p->discounted_price ?? $p->price),
            ]);

        $order = DB::transaction(function () use ($request, $data, $prices) {
            $order = $request->user()->orders()->create([
                'status' => 'PENDING',
                'payment_method' => $data['paymentMethod'],
                'total' => $data['total'],
                'address_id' => $data['addressId'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['productId'],
                    'quantity' => $item['quantity'],
                    'price' => $prices[$item['productId']] ?? 0,
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
