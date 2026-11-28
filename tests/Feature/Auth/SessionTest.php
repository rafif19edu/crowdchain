<?php

use App\Models\User;

it('logs in with correct credentials', function () {
    $user = donor(['email' => 'a@example.test', 'password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->ulid);

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials without saying which part was wrong', function () {
    donor(['email' => 'a@example.test', 'password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');

    $this->assertGuest();
});

it('rate-limits login after 5 failed attempts', function () {
    donor(['email' => 'a@example.test']);

    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'wrong'])
        ->assertStatus(429)
        ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Too many login attempts'));
});

it('refuses login for suspended users', function () {
    User::factory()->suspended()->create(['email' => 's@example.test', 'password' => 'secret123']);

    $this->postJson('/api/v1/auth/login', ['email' => 's@example.test', 'password' => 'secret123'])
        ->assertForbidden();

    $this->assertGuest();
});

it('returns the current user and logs out', function () {
    $user = donor();

    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', $user->email);
    $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();
    $this->assertGuest('web');
});

it('returns 401 for /me when not logged in', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('creates the account but does not log in for stateless clients', function () {
    $this->withHeader('Origin', 'https://elsewhere.example')
        ->postJson('/api/v1/auth/register', [
            'name' => 'Postman', 'email' => 'pm@example.test', 'password' => 'secret123',
            'password_confirmation' => 'secret123', 'role' => 'donor', 'consent' => true,
        ])->assertCreated();

    $this->assertGuest();
});

it('explains that login needs the web app when there is no session', function () {
    donor(['email' => 'a@example.test', 'password' => 'secret123']);

    $this->withHeader('Origin', 'https://elsewhere.example')
        ->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'secret123'])
        ->assertStatus(400);
});
