<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dailzo backend is running',
            'data' => [
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'service' => 'dailzo-backend',
            ],
        ]);
    }
}
