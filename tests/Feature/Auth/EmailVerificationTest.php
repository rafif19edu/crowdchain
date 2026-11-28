<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl(User $user, ?string $hash = null): string
{
    return URL::temporarySignedRoute('api.v1.verification.verify', now()->addHour(), [
        'user' => $user->ulid,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifies the email from the signed link', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(verificationUrl($user))->assertOk()->assertJsonPath('message', 'Email verified.');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('redirects a browser to the dashboard after verifying', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))->assertRedirect('/dashboard?verified=1');
});

it('rejects a link with the wrong hash or a broken signature', function () {
    $user = User::factory()->unverified()->create();

    $this->getJson(verificationUrl($user, sha1('other@example.test')))->assertForbidden();
    $this->getJson("/api/v1/auth/email/verify/{$user->ulid}/".sha1($user->email))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->postJson('/api/v1/auth/email/verification-notification')->assertStatus(202);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('builds the emailed link with the user ULID', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
        return str_contains($n->toMail($user)->actionUrl, "/api/v1/auth/email/verify/{$user->ulid}/");
    });
});
