<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Services;

use App\Features\Resume\App\Contracts\ResumeDataReaderInterface;
use App\Features\Resume\Domain\Entities\ResumeConfig;

/**
 * The resume PDF's content-hash and object-path policy: SHA-256 of the
 * source `updated_at` stamps, the config stamp and the layout JSON.
 */
final class ResumePdfPathResolver
{
    public function __construct(
        private readonly ResumeDataReaderInterface $reader,
    ) {
    }

    public function hashFor(ResumeConfig $config): string
    {
        return hash('sha256', json_encode([
            'stamps' => $this->reader->sourceStamps(),
            'config_updated_at' => $config->toUpdatedAt()->format('Y-m-d H:i:s'),
            'layout' => $config->toLayout(),
        ], JSON_THROW_ON_ERROR));
    }

    public function pathFor(string $locale, string $hash): string
    {
        return "about/resume_{$locale}_{$hash}.pdf";
    }
}
