<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('role:fundraiser') or 'role:donor,fundraiser'. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        if (! $user || ! $user->hasRole(...$allowed)) {
            return ApiResponse::error('This action is unauthorized.', 403);
        }

        return $next($request);
    }
}
