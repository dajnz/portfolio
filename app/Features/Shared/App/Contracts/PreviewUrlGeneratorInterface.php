<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Generates the signed preview URLs of the admin edit forms
 * (routes.md:58-60,151 — a 1-hour `URL::temporarySignedRoute()` value).
 * An abstraction over the Laravel URL facade so the app layer never
 * touches it directly; the framework-backed implementation lives on the
 * infra layer.
 */
interface PreviewUrlGeneratorInterface
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function temporarySigned(string $routeName, int $expiresInSeconds, array $parameters): string;
}
