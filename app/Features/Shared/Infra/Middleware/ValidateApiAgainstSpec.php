<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `validate_api_spec` middleware alias (openapi.md:279-299) — dev-time
 * OpenAPI contract drift detection. Active only when APP_DEBUG=true;
 * validates the JSON response of `/api/*` requests (and the dual-response
 * `GET /blog` / `GET /projects` web routes) against `docs/openapi/openapi.yaml`.
 * Violations are logged at `warning` — non-blocking, so it never halts dev
 * iteration. The parsed spec is cached in memory for the request lifecycle.
 * Paths without a matching operation in the spec are skipped.
 */
final class ValidateApiAgainstSpec
{
    private static ?ResponseValidator $validator = null;

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

            foreach (self::pathFinder($request)->search() as $operation) {
                $validator->validate($operation, $this->toPsr7($response));
            }
        } catch (Throwable $exception) {
            Log::warning('OpenAPI response validation failed: '.$exception->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        }
    }

    private static function pathFinder(Request $request): PathFinder
    {
        return new PathFinder(self::validator()->getSchema(), $request->path(), $request->method());
    }

    private function validator(): ResponseValidator
    {
        if (self::$validator === null) {
            self::$validator = (new ValidatorBuilder())
                ->fromYamlFile(base_path('docs/openapi/openapi.yaml'))
                ->getResponseValidator();
        }

        return self::$validator;
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
