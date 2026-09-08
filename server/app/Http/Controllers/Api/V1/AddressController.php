<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($addresses, 'Addresses fetched successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateAddress($request, creating: true);
        $user = $request->user();

        if ($data['isDefault'] ?? false) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'label' => $data['label'],
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'pincode' => $data['pincode'],
            'is_default' => $data['isDefault'] ?? false,
        ]);

        return ApiResponse::success($address, 'Address created successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->whereKey($id)->first();
        if (! $address) {
            return ApiResponse::success(null, 'Address not found');
        }

        $data = $this->validateAddress($request, creating: false);

        if ($data['isDefault'] ?? false) {
            $user->addresses()->whereKeyNot($id)->update(['is_default' => false]);
        }

        foreach (['label', 'line1', 'line2', 'city', 'state', 'pincode'] as $field) {
            if (array_key_exists($field, $data)) {
                $address->{$field} = $data[$field];
            }
        }
        if (array_key_exists('isDefault', $data)) {
            $address->is_default = $data['isDefault'];
        }
        $address->save();

        return ApiResponse::success($address, 'Address updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $address = $request->user()->addresses()->whereKey($id)->first();
        if (! $address) {
            return ApiResponse::success(null, 'Address not found');
        }

        $address->delete();

        return ApiResponse::success(['id' => $id], 'Address removed successfully');
    }

    private function validateAddress(Request $request, bool $creating): array
    {
        return $request->validate([
            'label' => [$creating ? 'required' : 'sometimes', 'string'],
            'line1' => [$creating ? 'required' : 'sometimes', 'string'],
            'line2' => ['sometimes', 'nullable', 'string'],
            'city' => [$creating ? 'required' : 'sometimes', 'string'],
            'state' => ['sometimes', 'nullable', 'string'],
            'pincode' => [$creating ? 'required' : 'sometimes', 'string'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);
    }
}
