<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** JSON version of Laravel's "verified" middleware (FR-UM-02). */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return ApiResponse::error('Please verify your email address first.', 403);
        }

        return $next($request);
    }
}
