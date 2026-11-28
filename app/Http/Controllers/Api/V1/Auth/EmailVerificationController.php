<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** FR-UM-02 */
class EmailVerificationController extends Controller
{
    /**
     * GET /auth/email/verify/{user}/{hash} — the link in the email.
     * The URL is signed, so it cannot be forged; no login is needed.
     */
    public function verify(Request $request, User $user, string $hash): JsonResponse|RedirectResponse
    {
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return ApiResponse::error('This verification link is invalid.', 403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $request->expectsJson()
            ? ApiResponse::success(null, 'Email verified.')
            : redirect('/dashboard?verified=1');
    }

    /** POST /auth/email/verification-notification */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Your email is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification link sent.', 202);
    }
}
