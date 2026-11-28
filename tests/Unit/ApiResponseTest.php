<?php

use App\Support\ApiResponse;

it('builds a success envelope', function () {
    $response = ApiResponse::success(['id' => 1], 'Saved.', 201, ['total' => 1]);

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true))->toBe([
            'data' => ['id' => 1],
            'message' => 'Saved.',
            'meta' => ['total' => 1],
        ]);
});

it('omits meta when none is given', function () {
    expect(ApiResponse::success([])->getData(true))->not->toHaveKey('meta');
});

it('builds an error envelope', function () {
    $response = ApiResponse::error('Nope.', 409, ['stage' => ['Not releasable.']]);

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toBe(['message' => 'Nope.', 'errors' => ['stage' => ['Not releasable.']]]);
});
