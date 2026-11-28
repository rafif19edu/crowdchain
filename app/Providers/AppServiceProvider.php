<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Stable names in polymorphic columns (documents, audit_logs, notifications)
        // instead of PHP class names, so renaming a class never breaks old rows.
        Relation::enforceMorphMap([
            'user' => User::class,
            'organization' => Organization::class,
            'document' => Document::class,
        ]);

        // The verification link in the email points to our API route (by ULID, signed).
        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute(
            'api.v1.verification.verify',
            now()->addMinutes(60),
            ['user' => $user->ulid, 'hash' => sha1($user->getEmailForVerification())],
        ));

        // The reset link opens the React page, which then calls POST /auth/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => url('/reset-password').'?'.http_build_query([
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
