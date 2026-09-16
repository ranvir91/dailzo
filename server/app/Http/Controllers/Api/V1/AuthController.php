<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string']]);

        $otp = $this->otp->generate($data['phone']);

        $payload = ['phone' => $data['phone'], 'expiresIn' => config('dailzo.otp_ttl')];
        if (config('dailzo.otp_debug')) {
            $payload['otp'] = $otp;
        }

        return ApiResponse::success($payload, 'OTP sent successfully');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        return $this->authenticate($request, 'OTP verified successfully');
    }

    public function login(Request $request): JsonResponse
    {
        return $this->authenticate($request, 'Login successful');
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $data = $request->validate(['refreshToken' => ['required', 'string']]);

        $record = RefreshToken::where('token', $this->hash($data['refreshToken']))->active()->first();
        if (! $record) {
            return ApiResponse::error('Invalid refresh token', ['Refresh token is invalid'], 401);
        }

        $user = $record->user;
        if (! $user) {
            return ApiResponse::error('User not found', ['The user associated with this refresh token was not found'], 404);
        }

        $accessToken = $user->createToken('mobile')->plainTextToken;

        return ApiResponse::success(
            ['user' => $user, 'accessToken' => $accessToken],
            'Token refreshed successfully',
        );
    }

    private function authenticate(Request $request, string $message): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        if (! $this->otp->verify($data['phone'], $data['otp'])) {
            return ApiResponse::error('Invalid OTP', ['The provided OTP is invalid or expired'], 400);
        }

        $user = User::firstOrCreate(
            ['phone' => $data['phone']],
            ['role' => 'CUSTOMER', 'name' => 'New Customer'],
        );

        return ApiResponse::success($this->issueTokens($user), $message);
    }

    private function issueTokens(User $user): array
    {
        $refreshToken = Str::random(64);

        $user->refreshTokens()->create([
            'token' => $this->hash($refreshToken),
            'expires_at' => now()->addDays(config('dailzo.refresh_token_ttl_days')),
        ]);

        return [
            'user' => $user,
            'accessToken' => $user->createToken('mobile')->plainTextToken,
            'refreshToken' => $refreshToken,
        ];
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
