<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the response envelope every Dailzo API endpoint returns:
 *
 *   { "success": true,  "data": {...}, "message": "..." }
 *   { "success": false, "message": "...", "errors": [...] }
 *
 * Kept identical to the old NestJS `successResponse()` / `errorResponse()` helpers
 * so the Flutter app and admin panel need no changes.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Request successful', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?: [$message],
        ], $status);
    }
}
