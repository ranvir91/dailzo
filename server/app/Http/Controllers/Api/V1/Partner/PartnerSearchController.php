<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerSearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 3;

    /** For the "reassign to..." picker — active partners matching name/phone. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string']]);
        $term = trim($data['query']);

        if (mb_strlen($term) < self::MIN_QUERY_LENGTH) {
            return ApiResponse::success([], 'Type at least '.self::MIN_QUERY_LENGTH.' characters to search');
        }

        $partners = DeliveryPartner::active()
            ->where('id', '!=', $request->user()->id)
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        return ApiResponse::success($partners, 'Partners fetched successfully');
    }
}
