<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placeholder notification endpoint (as in the old backend). Wire FCM here
 * post-cutover — see docs/php-migration-plan.md.
 */
class NotificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'sent' => true,
            'channel' => $request->input('channel', 'fcm'),
            'event' => $request->input('event', 'ORDER_CONFIRMED'),
        ], 'Notification queued successfully');
    }
}
