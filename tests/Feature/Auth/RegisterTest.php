<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function registration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nur Aisyah',
        'email' => 'aisyah@example.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'role' => 'donor',
        'consent' => true,
    ], $overrides);
}

it('registers a donor, logs them in and sends a verification email', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', registration())
        ->assertCreated()
        ->assertJsonPath('data.role', 'donor')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonMissingPath('data.password');

    $user = User::firstWhere('email', 'aisyah@example.test');
    expect($user->ulid)->toHaveLength(26);
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('registers a fundraiser', function () {
    $this->postJson('/api/v1/auth/register', registration(['role' => 'fundraiser']))
        ->assertCreated()
        ->assertJsonPath('data.role', 'fundraiser');
});

it('never lets the public register an admin', function () {
    $this->postJson('/api/v1/auth/register', registration(['role' => 'admin']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

it('validates password strength, confirmation, consent and unique email', function () {
    donor(['email' => 'taken@example.test']);

    $this->postJson('/api/v1/auth/register', registration([
        'email' => 'taken@example.test',
        'password' => 'short',
        'password_confirmation' => 'different',
        'consent' => false,
    ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'consent']);
});
