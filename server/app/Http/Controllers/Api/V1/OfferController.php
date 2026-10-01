<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OfferController extends Controller
{
    /** Public — active, in-window offers for the mobile home page. */
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            Offer::active()->orderBy('sort_order')->orderByDesc('created_at')->get(),
            'Offers fetched successfully',
        );
    }
}
