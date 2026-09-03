<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Swagger;

use App\Features\Shared\Infra\OpenApi\OpenApiSpecDocument;

/**
 * Keeps the l5-swagger docs file (`storage/api-docs/api-docs.json`, per
 * `config/l5-swagger.php` `paths.docs`/`paths.docs_json`) in sync with the
 * authoritative `docs/openapi/openapi.yaml` contract. l5-swagger's own
 * generator scans PHP annotations — it cannot consume a YAML spec — so the
 * UI serves the dereferenced OpenAPI document instead (openapi.md:269-275:
 * "Points `docs.openapi.paths.definitions` to `docs/openapi/openapi.yaml`").
 * `generate_always` stays `false`; the file is rewritten only when it is
 * missing or the spec is newer.
 */
final class SwaggerUiService
{
    public function __construct(
        private readonly OpenApiSpecDocument $document,
    ) {
    }

    /**
     * @return string|null The written docs file path, or null when l5-swagger
     *                     is not installed (config absent or empty — the
     *                     production build caches an empty config).
     */
    public function sync(): ?string
    {
        if (empty(config('l5-swagger'))) {
            return null;
        }

        $docsDir = (string) config('l5-swagger.documentations.default.paths.docs', storage_path('api-docs'));
        $docsFile = (string) config('l5-swagger.documentations.default.paths.docs_json', 'api-docs.json');

        $path = rtrim($docsDir, '/').'/'.$docsFile;

        if (is_file($path) && filemtime($path) >= filemtime($this->document->specPath())) {
            return $path;
        }

        if (! is_dir($docsDir) && ! mkdir($docsDir, 0775, true) && ! is_dir($docsDir)) {
            throw new \RuntimeException('Unable to create the l5-swagger docs directory: '.$docsDir);
        }

        file_put_contents($path, $this->document->dereferencedJson());

        return $path;
    }
}
