<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for admin-only API routes. Runs after `auth:sanctum`, so a user is present;
 * mirrors the old NestJS AdminGuard (role must be ADMIN).
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'ADMIN') {
            return ApiResponse::error('Admin access required', ['Admin access required'], 403);
        }

        return $next($request);
    }
}
