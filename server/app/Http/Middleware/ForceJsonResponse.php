<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API clients (the Flutter app, the admin panel) send `Content-Type: application/json`
 * but not always an `Accept` header. Forcing it here makes Laravel render every error
 * — auth, validation, 404 — as JSON in our envelope instead of an HTML page or a
 * redirect to a non-existent `login` route.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
