<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\UploadPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('category')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($products, 'Products fetched successfully');
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::with('category')->find($id);

        return ApiResponse::success(
            $product,
            $product ? 'Product fetched successfully' : 'Product not found',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, creating: true);

        $category = $this->resolveCategory($data['categoryId'] ?? null, $data['category'] ?? null);
        if (! $category) {
            return ApiResponse::success(null, 'Category not found');
        }

        $product = Product::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $category->id,
            'price' => $data['price'],
            'discounted_price' => $data['discountedPrice'] ?? null,
            'stock' => $data['stock'],
            'images' => UploadPath::normalizeMany($data['images'] ?? []),
            'sort_order' => $data['sortOrder'] ?? 0,
        ]);

        return ApiResponse::success($product->load('category'), 'Product created successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);
        if (! $product) {
            return ApiResponse::success(null, 'Product not found');
        }

        $data = $this->validated($request, creating: false);

        foreach (['name', 'description', 'price', 'stock'] as $field) {
            if (array_key_exists($field, $data)) {
                $product->{$field} = $data[$field];
            }
        }
        if (array_key_exists('discountedPrice', $data)) {
            $product->discounted_price = $data['discountedPrice'];
        }
        if (array_key_exists('sortOrder', $data)) {
            $product->sort_order = $data['sortOrder'] ?? 0;
        }
        if (array_key_exists('images', $data)) {
            $product->images = UploadPath::normalizeMany($data['images'] ?? []);
        }
        if (! empty($data['categoryId']) || ! empty($data['category'])) {
            $category = $this->resolveCategory($data['categoryId'] ?? null, $data['category'] ?? null);
            if ($category) {
                $product->category_id = $category->id;
            }
        }

        $product->save();

        return ApiResponse::success($product->load('category'), 'Product updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);
        if (! $product) {
            return ApiResponse::success(null, 'Product not found');
        }

        $product->delete();

        return ApiResponse::success(null, 'Product deleted successfully');
    }

    /**
     * Admin forms send numbers as strings and an empty string for "clear this".
     * '' → null for discountedPrice (nullable), '' → 0/unchanged for sortOrder.
     */
    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'categoryId' => ['sometimes', 'nullable', 'string'],
            'price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'discountedPrice' => ['sometimes', 'nullable'],
            'stock' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'sortOrder' => ['sometimes', 'nullable'],
        ];

        $data = $request->validate($rules);

        if (array_key_exists('discountedPrice', $data)) {
            $data['discountedPrice'] = ($data['discountedPrice'] === '' || $data['discountedPrice'] === null)
                ? null
                : (float) $data['discountedPrice'];
        }
        if (array_key_exists('sortOrder', $data)) {
            $data['sortOrder'] = ($data['sortOrder'] === '' || $data['sortOrder'] === null)
                ? null
                : (int) $data['sortOrder'];
        }

        return $data;
    }

    private function resolveCategory(?string $categoryId, ?string $slugOrName): ?Category
    {
        if ($categoryId) {
            return Category::find($categoryId);
        }

        if (! $slugOrName) {
            return null;
        }

        return Category::where('slug', $slugOrName)->first();
    }
}
