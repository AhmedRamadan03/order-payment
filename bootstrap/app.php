<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $isApiRequest = fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                'The given data was invalid.',
                'VALIDATION_ERROR',
                422,
                $exception->errors(),
            );
        });

        $exceptions->render(function (JWTException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('The authentication token is invalid or expired.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error('The requested resource was not found.', 'RESOURCE_NOT_FOUND', 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$code, $message] = match ($status) {
                403 => ['FORBIDDEN', 'You are not allowed to perform this action.'],
                404 => ['RESOURCE_NOT_FOUND', 'The requested resource was not found.'],
                405 => ['METHOD_NOT_ALLOWED', 'The HTTP method is not supported for this endpoint.'],
                429 => ['TOO_MANY_REQUESTS', 'Too many requests. Please try again later.'],
                default => $status >= 500
                    ? ['INTERNAL_SERVER_ERROR', 'An unexpected server error occurred.']
                    : ['HTTP_ERROR', 'The request could not be completed.'],
            };

            return ApiResponse::error($message, $code, $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return ApiResponse::error(
                'An unexpected server error occurred.',
                'INTERNAL_SERVER_ERROR',
                500,
            );
        });
    })->create();
