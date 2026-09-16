<?php

namespace App\Http\Middleware;

use App\Models\DeliveryPartner;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the delivery-partner API. Runs after `auth:sanctum`; the resolved
 * token owner must be a DeliveryPartner (Sanctum tokens are polymorphic, so a
 * customer/admin token would otherwise also pass `auth:sanctum` here).
 */
class PartnerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->user();

        if (! $partner instanceof DeliveryPartner) {
            return ApiResponse::error('Delivery partner access required', ['Delivery partner access required'], 403);
        }

        if (! $partner->is_active) {
            return ApiResponse::error('This delivery partner account is inactive', ['Account inactive'], 403);
        }

        return $next($request);
    }
}
