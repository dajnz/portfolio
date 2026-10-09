<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use App\Features\Shared\Infra\OpenApi\OpenApiSpecDocument;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `validate_api_spec` middleware alias — dev-time OpenAPI contract drift
 * detection. Active only when APP_DEBUG=true; validates the JSON response of
 * `/api/*` requests (and the dual-response `GET /blog` / `GET /projects` web
 * routes) against `docs/openapi/openapi.yaml`. Violations are logged at
 * `warning` — non-blocking, so it never halts dev iteration. The parsed spec
 * is cached in memory for the request lifecycle. Paths without a matching
 * operation in the spec are skipped.
 *
 * The root `openapi.yaml` is a $ref-stitched document; the external references
 * are dereferenced once per process by `OpenApiSpecDocument` (the OpenAPI
 * validator cannot resolve them).
 */
final class ValidateApiAgainstSpec
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->hasDebugModeEnabled()) {
            return $response;
        }

        if (! $request->is('api/*') && ! $request->wantsJson()) {
            return $response;
        }

        $this->validate($request, $response);

        return $response;
    }

    private function validate(Request $request, Response $response): void
    {
        try {
            $validator = $this->validator();

            foreach ($this->pathFinder($request)->search() as $operation) {
                $validator->validate($operation, $this->toPsr7($response));
            }
        } catch (Throwable $exception) {
            Log::warning('OpenAPI response validation failed: '.$exception->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }
    }

    private function pathFinder(Request $request): PathFinder
    {
        return $this->document()->pathFinder($request->path(), $request->method());
    }

    private function validator(): ResponseValidator
    {
        return $this->document()->responseValidator();
    }

    private function document(): OpenApiSpecDocument
    {
        return app(OpenApiSpecDocument::class);
    }

    private function toPsr7(Response $response): ResponseInterface
    {
        return new \GuzzleHttp\Psr7\Response(
            $response->getStatusCode(),
            $response->headers->all(),
            (string) $response->getContent(),
        );
    }
}
