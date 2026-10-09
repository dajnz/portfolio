<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The exception → response mapping, wired as the single `render` callback in
 * `bootstrap/app.php`:
 *
 * - API / JSON-expecting requests get the `{ "message": ... }` envelope
 *   (per-status messages from the `errors` locale group; `ValidationException`
 *   falls through to Laravel's default `{ message, errors }` shape).
 * - Inertia requests (`X-Inertia`) get the `Shared/CrossSurface/Pages/ErrorPage`
 *   Inertia response with `{ status, message, request_id }` props.
 *   `ValidationException` and `AuthenticationException` fall through — the
 *   defaults (redirect back with errors / redirect to login) are the Inertia
 *   form contract.
 * - Everything else returns `null`, so the framework renders the Blade
 *   `errors/{status}` views (`resources/views/errors/`).
 *
 * `ValidationException`/`AuthenticationException` are checked before the mapping
 * because Laravel's `prepareException()` has already converted
 * `ModelNotFoundException`/`AuthorizationException`/`TokenMismatchException`
 * into `HttpException` instances (the original is preserved via `getPrevious()`).
 */
final class ErrorResponseFactory
{
    public function make(Throwable $exception, Request $request): ?Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->jsonResponse($exception);
        }

        if ($request->header('X-Inertia')) {
            return $this->inertiaResponse($exception, $request);
        }

        return null;
    }

    private function jsonResponse(Throwable $exception): ?JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return null;
        }

        [$status, $message] = $this->statusAndMessage($exception);

        if ($status >= 500 && app()->hasDebugModeEnabled()) {
            return null;
        }

        return response()->json(['message' => $message], $status);
    }

    private function inertiaResponse(Throwable $exception, Request $request): ?Response
    {
        if ($exception instanceof ValidationException || $exception instanceof AuthenticationException) {
            return null;
        }

        [$status, $message] = $this->statusAndMessage($exception);

        if ($status >= 500 && app()->hasDebugModeEnabled()) {
            return null;
        }

        $requestId = $request->attributes->get('request_id');
        if ($requestId === null) {
            // Unmatched paths never reach the `web` group (the router
            // throws before any route middleware runs), so `log_context`
            // cannot provide a request_id — generate one so the error
            // page still has a reference ID for bug reports.
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }

        $response = Inertia::render('Shared/CrossSurface/Pages/ErrorPage', [
            'status' => $status,
            'message' => $message,
            'request_id' => $requestId,
        ])->toResponse($request);

        if (! $response->headers->has('X-Request-ID')) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        $response->setStatusCode($status);

        return $response;
    }

    /**
     * @return array{int, string}
     */
    private function statusAndMessage(Throwable $exception): array
    {
        if ($exception instanceof AuthenticationException) {
            return [401, __('errors.unauthenticated')];
        }

        if ($exception instanceof ThrottleRequestsException) {
            return [429, __('errors.too_many_attempts')];
        }

        if ($exception instanceof HttpExceptionInterface) {
            return [$exception->getStatusCode(), $this->messageForStatus($exception)];
        }

        return [500, __('errors.server_error')];
    }

    private function messageForStatus(HttpExceptionInterface $exception): string
    {
        $previous = $exception->getPrevious();

        if ($exception instanceof NotFoundHttpException && $previous instanceof ModelNotFoundException) {
            return __('errors.entity_not_found', [
                'entity' => $this->entityName($previous->getModel()),
            ]);
        }

        return match ($exception->getStatusCode()) {
            401 => __('errors.unauthenticated'),
            403 => __('errors.unauthorized'),
            404 => __('errors.not_found'),
            405 => __('errors.method_not_allowed'),
            419 => __('errors.page_expired'),
            422 => __('errors.validation_failed'),
            429 => __('errors.too_many_attempts'),
            503 => __('errors.service_unavailable'),
            default => __('errors.server_error'),
        };
    }

    private function entityName(string $model): string
    {
        return Str::title(Str::snake(str_replace('Model', '', class_basename($model)), ' '));
    }
}
