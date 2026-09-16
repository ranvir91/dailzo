<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $partner = DeliveryPartner::where('phone', $data['phone_number'])->first();

        if (! $partner || ! $partner->password || ! Hash::check($data['password'], $partner->password)) {
            return ApiResponse::error('Invalid phone number or password', ['Invalid credentials'], 401);
        }

        if (! $partner->is_active) {
            return ApiResponse::error('This delivery partner account is inactive', ['Account inactive'], 401);
        }

        $refreshToken = Str::random(64);
        $partner->refreshTokens()->create([
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(config('dailzo.refresh_token_ttl_days')),
        ]);

        return ApiResponse::success([
            'token' => $partner->createToken('partner-mobile')->plainTextToken,
            'refreshToken' => $refreshToken,
            'partnerId' => $partner->id,
            'name' => $partner->name,
        ], 'Login successful');
    }
}
