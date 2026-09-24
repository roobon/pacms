<?php

namespace App\Support\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Converts exceptions into the PACMS JSON error envelope:
 *   { "message": "...", "code": "validation_failed", "errors": { ... } }
 *
 * Server errors never expose exception details outside debug mode.
 */
final class ApiErrorRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        419 => 'csrf_mismatch',
        422 => 'validation_failed',
        429 => 'too_many_requests',
        503 => 'maintenance',
    ];

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $message, $errors, $headers] = match (true) {
            $e instanceof ValidationException => [422, $e->getMessage(), $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'Unauthenticated.', null, []],
            $e instanceof AuthorizationException => [403, 'This action is unauthorized.', null, []],
            $e instanceof ModelNotFoundException => [404, 'Not found.', null, []],
            $e instanceof TokenMismatchException => [419, 'Your session has expired. Please refresh and try again.', null, []],
            $e instanceof ThrottleRequestsException => [429, 'Too many requests. Please slow down.', null, $e->getHeaders()],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $e->getMessage() !== '' ? $e->getMessage() : self::defaultMessage($e->getStatusCode()),
                null,
                $e->getHeaders(),
            ],
            default => [500, 'Server error.', null, []],
        };

        $body = ['message' => $message, 'code' => self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'error')];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        if ($status >= 500 && config('app.debug')) {
            $body['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        return new JsonResponse($body, $status, $headers);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            404 => 'Not found.',
            403 => 'This action is unauthorized.',
            405 => 'Method not allowed.',
            503 => 'Service temporarily unavailable.',
            default => 'Request failed.',
        };
    }
}
