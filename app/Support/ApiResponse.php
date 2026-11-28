<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

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
     * A page of results with the standard meta block (docs/15 §1.3).
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $page, string $resource, ?string $message = null): JsonResponse
    {
        return self::success(
            data: $resource::collection($page->items())->resolve(),
            message: $message,
            meta: [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => $errors], $status);
    }
}
