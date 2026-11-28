<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel app and use a fresh PostgreSQL test database
| (crowdchain_test, see phpunit.xml) for every test.
|
| Requests carry an Origin header from the app's own domain so Sanctum treats
| them like the real same-origin SPA (session cookies, D-44).
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withHeader('Origin', 'http://localhost'))
    ->in('Feature');

function donor(array $attributes = []): User
{
    return User::factory()->donor()->create($attributes);
}

function fundraiser(array $attributes = []): User
{
    return User::factory()->fundraiser()->create($attributes);
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}
