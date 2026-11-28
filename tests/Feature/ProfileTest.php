<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('updates name and phone', function () {
    $user = donor();

    $this->actingAs($user)->patchJson('/api/v1/profile', ['name' => 'New Name', 'phone' => '012-3456789'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.phone', '012-3456789');
});

it('requires re-verification when the email changes', function () {
    Notification::fake();
    $user = donor();

    $this->actingAs($user)->patchJson('/api/v1/profile', ['email' => 'new@example.test'])
        ->assertOk()
        ->assertJsonPath('data.email_verified', false);

    Notification::assertSentTo($user->fresh(), VerifyEmail::class);
});

it('changes the password only with the current password', function () {
    $user = donor(['password' => 'secret123']);

    $this->actingAs($user)->putJson('/api/v1/profile/password', [
        'current_password' => 'wrong',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->actingAs($user)->putJson('/api/v1/profile/password', [
        'current_password' => 'secret123',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ])->assertOk();

    expect(Hash::check('newpass123', $user->fresh()->password))->toBeTrue();
});
