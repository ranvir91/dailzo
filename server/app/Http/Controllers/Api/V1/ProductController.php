<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\UploadPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'item_type' => $data['itemType'] ?? 'GOODS',
            'description' => $data['description'] ?? null,
            'sku' => $data['sku'] ?? null,
            'item_code' => $data['itemCode'] ?? null,
            'hsn_sac_code' => $data['hsnSacCode'] ?? null,
            'brand' => $data['brand'] ?? null,
            'unit' => $data['unit'] ?? null,
            'category_id' => $category->id,
            'price' => $data['price'],
            'mrp' => $data['mrp'] ?? null,
            'discounted_price' => $data['discountedPrice'] ?? null,
            'purchase_price' => $data['purchasePrice'] ?? null,
            'part_no' => $data['partNo'] ?? null,
            'tags' => $data['tags'] ?? [],
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

        $data = $this->validated($request, creating: false, ignoreId: $id);

        foreach (['name', 'description', 'price', 'stock'] as $field) {
            if (array_key_exists($field, $data)) {
                $product->{$field} = $data[$field];
            }
        }
        $passthrough = [
            'itemType' => 'item_type',
            'sku' => 'sku',
            'itemCode' => 'item_code',
            'hsnSacCode' => 'hsn_sac_code',
            'brand' => 'brand',
            'unit' => 'unit',
            'mrp' => 'mrp',
            'purchasePrice' => 'purchase_price',
            'partNo' => 'part_no',
        ];
        foreach ($passthrough as $requestKey => $column) {
            if (array_key_exists($requestKey, $data)) {
                $product->{$column} = $data[$requestKey];
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
        if (array_key_exists('tags', $data)) {
            $product->tags = $data['tags'] ?? [];
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
    private function validated(Request $request, bool $creating, ?string $ignoreId = null): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string'],
            'itemType' => ['sometimes', 'nullable', 'string', 'in:GOODS,SERVICE'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sku' => [
                'sometimes', 'nullable', 'string',
                Rule::unique('products', 'sku')->ignore($ignoreId),
            ],
            'itemCode' => ['sometimes', 'nullable', 'string'],
            'hsnSacCode' => ['sometimes', 'nullable', 'string'],
            'brand' => ['sometimes', 'nullable', 'string'],
            'unit' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'categoryId' => ['sometimes', 'nullable', 'integer'],
            'price' => [$creating ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'mrp' => ['sometimes', 'nullable'],
            'discountedPrice' => ['sometimes', 'nullable'],
            'purchasePrice' => ['sometimes', 'nullable'],
            'partNo' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
            'stock' => [$creating ? 'required' : 'sometimes', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'sortOrder' => ['sometimes', 'nullable'],
        ];

        $data = $request->validate($rules);

        foreach (['discountedPrice', 'mrp', 'purchasePrice'] as $nullableNumber) {
            if (array_key_exists($nullableNumber, $data)) {
                $data[$nullableNumber] = ($data[$nullableNumber] === '' || $data[$nullableNumber] === null)
                    ? null
                    : (float) $data[$nullableNumber];
            }
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
