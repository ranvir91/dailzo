<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cartFor($request), 'Cart fetched successfully');
    }

    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'productId' => ['required', 'string', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartFor($request);
        $cart->items()->create([
            'product_id' => $data['productId'],
            'quantity' => $data['quantity'],
        ]);

        return ApiResponse::success($cart->load('items'), 'Cart item added successfully');
    }

    public function updateItem(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $cart = $this->cartFor($request);
        $cart->items()->whereKey($id)->update(['quantity' => $data['quantity']]);

        return ApiResponse::success($cart->load('items'), 'Cart item updated successfully');
    }

    public function removeItem(Request $request, string $id): JsonResponse
    {
        $cart = $this->cartFor($request);
        $cart->items()->whereKey($id)->delete();

        return ApiResponse::success($cart->load('items'), 'Cart item removed successfully');
    }

    private function cartFor(Request $request): Cart
    {
        return Cart::firstOrCreate(['user_id' => $request->user()->id])->load('items');
    }
}
