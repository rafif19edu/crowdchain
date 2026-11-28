<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /** POST /auth/register (FR-UM-01, FR-UM-02) */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = $request->validated('role');
        $user->save();

        event(new Registered($user)); // sends the verification email

        // Browser (SPA) requests carry a session: log the new user straight in.
        // Stateless clients such as Postman just get the account created.
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return ApiResponse::success(new UserResource($user->load('organization')), 'Account created. Check your email to verify it.', 201);
    }
}
