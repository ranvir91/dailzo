<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $user = User::where('phone', $data['phone_number'])->where('role', 'DELIVERY_PARTNER')->first();

        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid phone number or password', ['Invalid credentials'], 401);
        }

        $partner = $user->deliveryPartnerProfile;

        if (! $partner || ! $partner->is_active) {
            return ApiResponse::error('This delivery partner account is inactive', ['Account inactive'], 401);
        }

        $refreshToken = Str::random(64);
        // Same refresh_tokens table every other login (customer/admin) uses —
        // now that partner login goes through users too, there's no reason for
        // a separate partner_refresh_tokens table.
        $user->refreshTokens()->create([
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(config('dailzo.refresh_token_ttl_days')),
        ]);

        return ApiResponse::success([
            'token' => $user->createToken('partner-mobile')->plainTextToken,
            'refreshToken' => $refreshToken,
            // Unchanged shape: still delivery_partners.id, not users.id — the
            // delivery app's existing API contract doesn't need to change.
            'partnerId' => $partner->id,
            'name' => $partner->name,
        ], 'Login successful');
    }
}
