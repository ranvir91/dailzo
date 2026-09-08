<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ApiResponse;
use App\Support\UploadPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($categories, 'Categories fetched successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'iconUrl' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sortOrder' => ['sometimes', 'nullable'],
        ]);

        $category = Category::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon_url' => UploadPath::normalize($data['iconUrl'] ?? null),
            'description' => $data['description'] ?? null,
            'sort_order' => $this->intOrNull($data['sortOrder'] ?? null) ?? 0,
        ]);

        return ApiResponse::success($category, 'Category created successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (! $category) {
            return ApiResponse::success(null, 'Category not found');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'iconUrl' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'nullable'],
        ]);

        if (array_key_exists('name', $data)) {
            $category->name = $data['name'];
            $category->slug = Str::slug($data['name']);
        }
        if (array_key_exists('iconUrl', $data)) {
            $category->icon_url = UploadPath::normalize($data['iconUrl']);
        }
        if (array_key_exists('description', $data)) {
            $category->description = $data['description'];
        }
        if (array_key_exists('isActive', $data)) {
            $category->is_active = $data['isActive'];
        }
        if (array_key_exists('sortOrder', $data)) {
            $category->sort_order = $this->intOrNull($data['sortOrder']) ?? 0;
        }

        $category->save();

        return ApiResponse::success($category, 'Category updated successfully');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $category = Category::find($id);
        if (! $category) {
            return ApiResponse::success(null, 'Category not found');
        }

        $data = $request->validate(['isActive' => ['required', 'boolean']]);
        $category->update(['is_active' => $data['isActive']]);

        return ApiResponse::success(
            $category,
            $data['isActive'] ? 'Category activated successfully' : 'Category deactivated successfully',
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::find($id);
        if (! $category) {
            return ApiResponse::success(null, 'Category not found');
        }

        $category->delete();

        return ApiResponse::success(null, 'Category deleted successfully');
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === '' || $value === null) ? null : (int) $value;
    }
}
