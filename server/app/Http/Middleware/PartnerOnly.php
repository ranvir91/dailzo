<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the delivery-partner API. Runs after `auth:sanctum`; the resolved
 * token owner must be a User with role DELIVERY_PARTNER (Sanctum tokens are
 * polymorphic, so a customer/admin/vendor token would otherwise also pass
 * `auth:sanctum` here).
 */
class PartnerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== 'DELIVERY_PARTNER' || ! $user->deliveryPartnerProfile) {
            return ApiResponse::error('Delivery partner access required', ['Delivery partner access required'], 403);
        }

        if (! $user->deliveryPartnerProfile->is_active) {
            return ApiResponse::error('This delivery partner account is inactive', ['Account inactive'], 403);
        }

        return $next($request);
    }
}
