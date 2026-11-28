<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the single JSON envelope every CrowdChain API response uses
 * (docs/15-api-design.md §1.1, NFR-MAINT-02).
 *
 *   success: { "data": ..., "message": ..., "meta": ... }
 *   error:   { "message": ..., "errors": ... }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        ?array $meta = null,
    ): JsonResponse {
        $body = ['data' => $data, 'message' => $message];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => $errors], $status);
    }
}
