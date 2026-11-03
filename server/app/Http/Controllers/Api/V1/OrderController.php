<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\CouponService;
use App\Services\InvoiceService;
use App\Services\SmsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const WITH = ['items.product', 'coupon'];

    public function __construct(
        private readonly CouponService $coupons,
        private readonly InvoiceService $invoices,
        private readonly SmsService $sms,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'addressId' => ['sometimes', 'nullable', 'integer'],
            'paymentMethod' => ['required', 'string'],
            // Kept for backward compatibility with older app builds; no
            // longer trusted for the order total (see below) — a client
            // could otherwise send any total it likes, coupon rules or not.
            'total' => ['required', 'numeric'],
            'couponCode' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $settings = StoreSetting::current();

        $products = Product::with('category')
            ->whereIn('id', collect($data['items'])->pluck('productId'))
            ->get()
            ->keyBy('id');

        $prices = $products->map(fn (Product $p) => (float) ($p->discounted_price ?? $p->price));

        // Snapshotted onto each order_item below so a category's tax_rate
        // changing later never rewrites an already-placed order's invoice.
        $taxRates = $products->map(fn (Product $p) => (float) ($p->category?->tax_rate ?? 0));

        // order_items.mrp snapshots the product's MRP (= price, now that
        // there's no separate mrp field) at order time, distinct from the
        // above $prices (what was actually charged) — the invoice's
        // MRP/discount columns must stay frozen even if price changes later.
        $mrps = $products->map(fn (Product $p) => (float) $p->price);

        $this->applyFreebiePricing($data['items'], $prices, $products, $settings);

        $subtotal = collect($data['items'])
            ->sum(fn (array $item) => ($prices[$item['productId']] ?? 0) * $item['quantity']);

        if ($settings->min_order_value_enabled && $subtotal < (float) $settings->min_order_value) {
            abort(400, "Minimum order value is ₹{$settings->min_order_value}. Please add more items to your cart.");
        }

        $order = DB::transaction(function () use ($request, $data, $prices, $taxRates, $mrps, $subtotal, $settings) {
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
                    'tax_rate' => $taxRates[$item['productId']] ?? 0,
                    'mrp' => $mrps[$item['productId']] ?? 0,
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

        if ($request->user()->phone) {
            $this->sms->send($request->user()->phone, 'order_placed', [
                'orderId' => $order->id,
                'total' => number_format((float) $order->total, 2),
            ]);
        }

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

    public function invoice(Request $request, string $id): JsonResponse|Response
    {
        $order = $this->find($request, $id);
        if (! $order) {
            return ApiResponse::success(null, 'Order not found');
        }

        $pdf = $this->invoices->render($order);

        return $pdf->stream("invoice-{$this->invoices->invoiceNumber($order)}.pdf");
    }

    private function find(Request $request, string $id): ?Order
    {
        $query = Order::with(self::WITH)->whereKey($id);

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->first();
    }

    /**
     * Overrides $prices in place for any item that qualifies as a freebie —
     * never trusts the client for this, re-derived from scratch server-side
     * every time:
     *
     *   - the product must have is_freebie = true
     *   - every OTHER item in the cart (i.e. not itself a freebie product),
     *     priced normally, must already total at least THIS product's own
     *     freebie_min_order_value (different freebie products may unlock at
     *     different spend levels)
     *   - the TOTAL quantity of freebie units charged at freebie price in
     *     this order — summed across every distinct freebie product, not
     *     just this one — must not exceed
     *     store_settings.max_freebie_quantity_per_order. This is a single
     *     store-wide cap rather than a per-product one: capping per product
     *     let a customer pick up 1 of freebie A *and* 1 of freebie B when
     *     only one freebie total was intended, which was a real bug.
     *
     * Freebie items are processed in cart order; once the global cap is
     * used up, any further freebie lines (whether the same or a different
     * product) revert to normal pricing — a line is never split into a
     * freebie-priced portion plus a normal-priced one.
     *
     * @param  array<int, array{productId: int, quantity: int}>  $items
     * @param  Collection<int, Product>  $products  Keyed by product id.
     */
    private function applyFreebiePricing(array $items, Collection $prices, Collection $products, StoreSetting $settings): void
    {
        $isFreebieItem = fn (array $item) => (bool) $products->get($item['productId'])?->is_freebie;

        $freebieItems = collect($items)->filter($isFreebieItem);
        if ($freebieItems->isEmpty()) {
            return;
        }

        $regularSubtotal = collect($items)
            ->reject($isFreebieItem)
            ->sum(fn (array $item) => ($prices[$item['productId']] ?? 0) * $item['quantity']);

        $remaining = (int) $settings->max_freebie_quantity_per_order;

        foreach ($freebieItems as $item) {
            if ($remaining <= 0) {
                break;
            }

            $product = $products->get($item['productId']);
            if ($regularSubtotal < (float) $product->freebie_min_order_value) {
                continue;
            }

            $qty = $item['quantity'];
            if ($qty <= $remaining) {
                $prices[$item['productId']] = (float) $product->freebie_price;
                $remaining -= $qty;
            }
        }
    }
}
