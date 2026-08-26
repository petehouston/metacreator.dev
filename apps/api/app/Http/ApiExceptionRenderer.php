<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Maps every exception onto the API's single error envelope (see docs/05).
 *
 * Clients get a stable machine-readable `code` and a human `message`; internals are
 * never leaked in production.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        // Exceptions that already know how to present themselves. Detected by
        // capability rather than by a list of classes, so a new domain exception with
        // a `render()` method is honoured without editing this file.
        if (method_exists($e, 'render')) {
            // Some domain exceptions take the request, some don't; pass it only when
            // the signature accepts it rather than relying on PHP tolerating extras.
            $accepts = (new ReflectionMethod($e, 'render'))->getNumberOfParameters() > 0;
            $rendered = $accepts ? $e->render($request) : $e->render();

            if ($rendered instanceof JsonResponse) {
                return self::withRequestId($rendered, $request);
            }
        }

        [$status, $code, $message, $details] = match (true) {
            $e instanceof ValidationException => [
                422, 'validation.failed', 'The given data was invalid.', $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                401, 'auth.unauthenticated', 'You need to sign in to do that.', [],
            ],
            $e instanceof AuthorizationException => [
                403, 'auth.forbidden', 'You do not have permission to do that.', [],
            ],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                404, 'resource.not_found', 'We could not find what you were looking for.', [],
            ],
            $e instanceof TooManyRequestsHttpException => [
                429, 'tool.rate_limited', 'Too many requests. Please slow down.', [],
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(), 'http.error', $e->getMessage() ?: 'Request failed.', [],
            ],
            default => [
                500,
                'server.error',
                // Never leak internals in production; the request id is what support
                // needs to find the trace in Sentry.
                app()->hasDebugModeEnabled() ? $e->getMessage() : 'Something went wrong on our end.',
                app()->hasDebugModeEnabled()
                    ? ['exception' => $e::class, 'file' => $e->getFile(), 'line' => $e->getLine()]
                    : [],
            ],
        };

        return self::withRequestId(new JsonResponse([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'details' => $details ?: null,
            ], fn ($v) => $v !== null),
        ], $status), $request);
    }

    private static function withRequestId(JsonResponse $response, Request $request): JsonResponse
    {
        $payload = $response->getData(true);
        $payload['error']['request_id'] = $request->headers->get('X-Request-Id', (string) str()->ulid());

        return $response->setData($payload);
    }
}
