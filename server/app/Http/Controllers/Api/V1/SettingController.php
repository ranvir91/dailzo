<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServicePincode;
use App\Models\StoreSetting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function showStore(): JsonResponse
    {
        return ApiResponse::success(StoreSetting::current(), 'Store settings fetched successfully');
    }

    public function updateStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deliveryCharges' => ['required', 'numeric'],
            'minOrderValue' => ['sometimes', 'numeric'],
            'minOrderValueEnabled' => ['sometimes', 'boolean'],
            'maintenanceMode' => ['sometimes', 'boolean'],
            'appVersion' => ['required', 'string'],
            'storeOpenTime' => ['required', 'string'],
            'storeCloseTime' => ['required', 'string'],
            'paymentMethods' => ['required', 'array'],
            'paymentMethods.*' => ['string'],
        ]);

        $settings = StoreSetting::current();
        $settings->fill([
            'delivery_charges' => $data['deliveryCharges'],
            'min_order_value' => $data['minOrderValue'] ?? $settings->min_order_value,
            'min_order_value_enabled' => $data['minOrderValueEnabled'] ?? $settings->min_order_value_enabled,
            'maintenance_mode' => $data['maintenanceMode'] ?? $settings->maintenance_mode,
            'app_version' => $data['appVersion'],
            'store_open_time' => $data['storeOpenTime'],
            'store_close_time' => $data['storeCloseTime'],
            'payment_methods' => $data['paymentMethods'],
        ])->save();

        return ApiResponse::success($settings->fresh(), 'Store settings updated successfully');
    }

    public function setMaintenanceMode(Request $request): JsonResponse
    {
        $data = $request->validate(['maintenanceMode' => ['required', 'boolean']]);

        $settings = StoreSetting::current();
        $settings->update(['maintenance_mode' => $data['maintenanceMode']]);

        return ApiResponse::success(
            $settings->fresh(),
            $data['maintenanceMode'] ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
        );
    }

    public function setMinOrderValue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'minOrderValue' => ['sometimes', 'numeric'],
        ]);

        $settings = StoreSetting::current();
        $settings->update([
            'min_order_value_enabled' => $data['enabled'],
            'min_order_value' => $data['minOrderValue'] ?? $settings->min_order_value,
        ]);

        return ApiResponse::success(
            $settings->fresh(),
            $data['enabled'] ? 'Minimum order value enforcement enabled' : 'Minimum order value enforcement disabled',
        );
    }

    public function listPincodes(): JsonResponse
    {
        return ApiResponse::success(
            ServicePincode::query()->latest()->get(),
            'Service pincodes fetched successfully',
        );
    }

    public function createPincode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pincode' => ['required', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $pincode = ServicePincode::create([
            'pincode' => trim($data['pincode']),
            'is_active' => $data['isActive'] ?? true,
        ]);

        return ApiResponse::success($pincode, 'Service pincode added successfully');
    }

    public function updatePincode(Request $request, string $id): JsonResponse
    {
        $pincode = ServicePincode::find($id);
        if (! $pincode) {
            return ApiResponse::success(null, 'Pincode not found');
        }

        $data = $request->validate([
            'pincode' => ['sometimes', 'string'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('pincode', $data)) {
            $pincode->pincode = trim($data['pincode']);
        }
        if (array_key_exists('isActive', $data)) {
            $pincode->is_active = $data['isActive'];
        }
        $pincode->save();

        return ApiResponse::success($pincode, 'Service pincode updated successfully');
    }

    public function deletePincode(string $id): JsonResponse
    {
        $pincode = ServicePincode::find($id);
        if (! $pincode) {
            return ApiResponse::success(null, 'Pincode not found');
        }

        $pincode->delete();

        return ApiResponse::success(null, 'Service pincode removed successfully');
    }

    public function checkPincode(string $pincode): JsonResponse
    {
        $trimmed = trim($pincode);
        $match = ServicePincode::where('pincode', $trimmed)->first();
        $serviceable = (bool) ($match && $match->is_active);

        return ApiResponse::success(
            ['pincode' => $trimmed, 'serviceable' => $serviceable],
            $serviceable ? 'Pincode is serviceable' : 'Pincode is not serviceable',
        );
    }

    /** GET /serviceable-pincodes — flat list of active pincode strings (mobile app). */
    public function activePincodes(): JsonResponse
    {
        return ApiResponse::success(
            ServicePincode::active()->orderBy('pincode')->pluck('pincode')->all(),
            'Serviceable pincodes fetched successfully',
        );
    }
}
