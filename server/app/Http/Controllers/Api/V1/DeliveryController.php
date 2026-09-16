<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
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

        $assignment = DeliveryAssignment::create([
            'order_id' => $data['orderId'],
            'delivery_partner_id' => $data['deliveryPartnerId'],
            'status' => 'ASSIGNED',
        ]);

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
