<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use Illuminate\Support\Facades\Config;

/**
 * Resolves the application version shown in the admin footer. An explicitly
 * configured `app.version` (production image bake, CI, tests) always wins;
 * otherwise the value is read from the repository's git state so local
 * development shows the current tag or short commit SHA.
 */
final class AppVersion
{
    private const FALLBACK = 'dev';

    public function __construct(
        private readonly GitVersionReader $reader,
        private readonly string $basePath,
    ) {
    }

    public function value(): string
    {
        $configured = Config::get('app.version');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->reader->read($this->basePath) ?? self::FALLBACK;
    }
}
