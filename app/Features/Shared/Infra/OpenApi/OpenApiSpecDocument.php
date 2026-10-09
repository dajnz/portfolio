<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\OpenApi;

use League\OpenAPIValidation\PSR7\PathFinder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Process-wide access to `docs/openapi/openapi.yaml` in a form the
 * `league/openapi-psr7-validator` can consume. The root document is a
 * $ref-stitched OpenAPI 3.1 spec: its `paths`, `components.schemas` and
 * `components.parameters` point at external feature/component YAML files
 * (`./features/*.yaml#/paths/...`, `../components/schemas.yaml#/...`). The
 * validator cannot resolve external references, so the document is dereferenced
 * into a single in-memory document; local `#/components/...` references are left
 * for the validator's native resolution.
 *
 * Consumed by the `validate_api_spec` middleware, the OpenAPI contract test
 * suite and the Swagger UI docs sync (`SwaggerUiService`).
 */
final class OpenApiSpecDocument
{
    private static ?array $root = null;

    private static ?array $dereferenced = null;

    private static ?string $dereferencedJson = null;

    private static ?ResponseValidator $responseValidator = null;

    public function specPath(): string
    {
        return base_path('docs/openapi/openapi.yaml');
    }

    /**
     * Raw parsed root document (`$ref`s intact).
     *
     * @return mixed[]
     */
    public function root(): array
    {
        return self::$root ??= (array) Yaml::parseFile($this->specPath());
    }

    /**
     * Root document with every external `$ref` replaced by its target,
     * recursively. Local references (`#/...`) are left untouched.
     *
     * @return mixed[]
     */
    public function dereferenced(): array
    {
        return self::$dereferenced ??= $this->resolveExternalRefs($this->root(), dirname($this->specPath()));
    }

    public function dereferencedJson(): string
    {
        return self::$dereferencedJson ??= (string) json_encode(
            $this->dereferenced(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    public function responseValidator(): ResponseValidator
    {
        return self::$responseValidator ??= (new ValidatorBuilder())
            ->fromJson($this->dereferencedJson())
            ->getResponseValidator();
    }

    public function pathFinder(string $path, string $method): PathFinder
    {
        return new PathFinder($this->responseValidator()->getSchema(), $path, $method);
    }

    /**
     * Every operation defined in the spec: `[{path, method, operationId}]`.
     * Used by the contract suite to prove per-operation coverage.
     *
     * @return array<int, array{path: string, method: string, operationId: string}>
     */
    public function operations(): array
    {
        $operations = [];

        foreach ($this->dereferenced()['paths'] ?? [] as $path => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($item as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['operationId'])) {
                    continue;
                }

                $operations[] = [
                    'path' => (string) $path,
                    'method' => (string) $method,
                    'operationId' => (string) $operation['operationId'],
                ];
            }
        }

        return $operations;
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

        $target = (array) Yaml::parseFile($path);

        foreach (array_filter(explode('/', $pointer), fn (string $segment): bool => $segment !== '') as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($target) || ! array_key_exists($segment, $target)) {
                throw new RuntimeException('Unresolvable OpenAPI reference: '.$ref);
            }
            $target = $target[$segment];
        }

        return $this->resolveExternalRefs($target, dirname($path));
    }
}
