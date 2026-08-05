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
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * `validate_api_spec` middleware alias (openapi.md:279-299) — dev-time
 * OpenAPI contract drift detection. Active only when APP_DEBUG=true;
 * validates the JSON response of `/api/*` requests (and the dual-response
 * `GET /blog` / `GET /projects` web routes) against `docs/openapi/openapi.yaml`.
 * Violations are logged at `warning` — non-blocking, so it never halts dev
 * iteration. The parsed spec is cached in memory for the request lifecycle.
 * Paths without a matching operation in the spec are skipped.
 *
 * The root `openapi.yaml` is a $ref-stitched document (README.md): its
 * `paths`, `components.schemas` and `components.parameters` point at
 * external feature/component YAML files (`./features/*.yaml#/paths/...`,
 * `../components/schemas.yaml#/...`). The OpenAPI validator cannot resolve
 * those external references, so the middleware dereferences them into a
 * single in-memory document before validation; local `#/components/...`
 * references are left for the validator's native resolution.
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
        return new PathFinder($this->validator()->getSchema(), $request->path(), $request->method());
    }

    private function validator(): ResponseValidator
    {
        if (self::$validator === null) {
            self::$validator = (new ValidatorBuilder())
                ->fromJson($this->dereferencedSpec())
                ->getResponseValidator();
        }

        return self::$validator;
    }

    private function dereferencedSpec(): string
    {
        $root = base_path('docs/openapi/openapi.yaml');

        return (string) json_encode(
            $this->resolveExternalRefs($this->readYaml($root), dirname($root)),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Replaces every node whose `$ref` points at an external YAML file
     * (`file.yaml#/pointer`) with the referenced content, recursively.
     * External paths are resolved relative to the file that contains the
     * reference. Local references (`#/...`) are left untouched — the
     * validator resolves them against the merged document.
     *
     * @param  mixed[]  $node
     * @return mixed[]
     */
    private function resolveExternalRefs(array $node, string $baseDir): array
    {
        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['$ref']) && is_string($value['$ref']) && ! str_starts_with($value['$ref'], '#')) {
                $node[$key] = $this->resolveExternalRef($value['$ref'], $baseDir);
            } else {
                $node[$key] = $this->resolveExternalRefs($value, $baseDir);
            }
        }

        return $node;
    }

    /**
     * Resolves an external reference and continues dereferencing inside
     * the referenced content (whose relative paths are based on the file
     * it came from).
     *
     * @return mixed[]
     */
    private function resolveExternalRef(string $ref, string $baseDir): array
    {
        [$file, $pointer] = explode('#', $ref, 2);

        $path = realpath($baseDir.'/'.$file);
        if ($path === false) {
            throw new RuntimeException('Unresolvable OpenAPI reference: '.$ref);
        }

        $target = $this->readYaml($path);

        foreach (array_filter(explode('/', $pointer), fn (string $segment): bool => $segment !== '') as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($target) || ! array_key_exists($segment, $target)) {
                throw new RuntimeException('Unresolvable OpenAPI reference: '.$ref);
            }
            $target = $target[$segment];
        }

        return $this->resolveExternalRefs($target, dirname($path));
    }

    /**
     * @return mixed[]
     */
    private function readYaml(string $path): array
    {
        return (array) Yaml::parseFile($path);
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
