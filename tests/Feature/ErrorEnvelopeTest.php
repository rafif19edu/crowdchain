<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Test-only routes that trigger each kind of error.
    Route::middleware('api')->prefix('api/v1/_test')->group(function () {
        Route::post('validate', fn (Request $r) => $r->validate(['email' => 'required|email']));
        Route::get('forbidden', fn () => abort(403));
        Route::get('private', fn () => 'secret')->middleware('auth:sanctum');
    });
});

it('returns 404 in the standard envelope for unknown API routes', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.', 'errors' => null]);
});

it('returns 422 with field errors', function () {
    $this->postJson('/api/v1/_test/validate', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonValidationErrors(['email']);
});

it('returns 403 in the standard envelope', function () {
    $this->getJson('/api/v1/_test/forbidden')
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action is unauthorized.', 'errors' => null]);
});

it('returns 401 for protected routes when not logged in', function () {
    $this->getJson('/api/v1/_test/private')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.', 'errors' => null]);
});
