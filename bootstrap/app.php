<?php

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie authentication for the same-origin React app (D-44).
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Map common exceptions onto the standard error envelope
        // { message, errors } (docs/15-api-design.md §1.1–1.2).
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('The given data was invalid.', 422, $e->errors());
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Unauthenticated.', 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('This action is unauthorized.', 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiResponse::error('Resource not found.', 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            $status = $e->getStatusCode();

            if ($isApi($request) && $status !== 500) {
                $defaults = [
                    401 => 'Unauthenticated.',
                    403 => 'This action is unauthorized.',
                    404 => 'Resource not found.',
                    409 => 'The request conflicts with the current state.',
                    419 => 'Your session has expired. Refresh the page and try again.',
                    429 => 'Too many requests. Please wait and try again.',
                ];

                return ApiResponse::error($e->getMessage() ?: ($defaults[$status] ?? 'Request failed.'), $status);
            }
        });
    })->create();
