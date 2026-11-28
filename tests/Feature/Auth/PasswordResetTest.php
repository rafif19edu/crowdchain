<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('sends a reset link and gives the same reply for unknown emails', function () {
    Notification::fake();
    $user = donor();

    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(202)->json('message');
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertStatus(202)->json('message');

    expect($known)->toBe($unknown);
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        return str_contains($n->toMail($user)->actionUrl, '/reset-password?token=');
    });
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = donor();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $n->token,
            'email' => $user->email,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        return true;
    });

    expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $user = donor();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'bogus',
        'email' => $user->email,
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
