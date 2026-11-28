<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** FR-UM-05 */
class ProfileController extends Controller
{
    /** PATCH /profile — a new email must be verified again. */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return ApiResponse::success(
            new UserResource($user->load('organization')),
            $emailChanged ? 'Profile updated. Verify your new email address.' : 'Profile updated.',
        );
    }

    /** PUT /profile/password */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill(['password' => $request->validated('password')])->save();

        return ApiResponse::success(null, 'Password changed.');
    }
}
