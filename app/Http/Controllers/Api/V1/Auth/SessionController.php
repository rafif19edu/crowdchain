<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sanctum SPA cookie sessions (D-44, docs/16 §1). */
class SessionController extends Controller
{
    /** POST /auth/login */
    public function store(LoginRequest $request): JsonResponse
    {
        // Cookie sessions only exist for requests from the CrowdChain web app's
        // own domain (SANCTUM_STATEFUL_DOMAINS). Anything else cannot log in.
        if (! $request->hasSession()) {
            return ApiResponse::error('Log in from the CrowdChain web app (its domain must be in SANCTUM_STATEFUL_DOMAINS).', 400);
        }

        $request->authenticate();

        $user = $request->user();

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();

            return ApiResponse::error('Your account is suspended. Contact the CrowdChain admin.', 403);
        }

        $request->session()->regenerate();

        return ApiResponse::success(new UserResource($user->load('organization')), 'Logged in.');
    }

    /** GET /auth/me */
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()->load('organization')));
    }

    /** POST /auth/logout */
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(null, 'Logged out.');
    }
}
