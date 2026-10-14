<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Builds absolute URLs for application paths — required by the surfaces that
 * must publish fully-qualified links (the RSS feed). An abstraction over the
 * framework's URL generator so the app layer never names it; the
 * framework-backed implementation lives on the infra layer.
 */
interface UrlGeneratorInterface
{
    public function to(string $path): string;
}
