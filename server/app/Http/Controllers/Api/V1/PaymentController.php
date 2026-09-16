<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placeholder payment flow (as in the old backend). Wire Razorpay here post-cutover
 * — see docs/php-migration-plan.md.
 */
class PaymentController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'paymentId' => 'pay-'.now()->getTimestampMs(),
            'status' => 'PENDING',
            'provider' => $request->input('provider', 'mock'),
        ], 'Payment created successfully');
    }

    public function webhook(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'paymentId' => $request->input('paymentId'),
            'status' => 'SUCCESS',
        ], 'Payment webhook processed');
    }
}
