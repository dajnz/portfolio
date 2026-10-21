<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Services;

use App\Features\Resume\App\Services\ResumePdfPathResolver;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\App\Contracts\AssetPathProviderInterface;

/**
 * The generated resume PDFs referenced by the assets bucket: one path per
 * active locale for the current content hash.
 */
final class GeneratedResumePdfAssetPathProvider implements AssetPathProviderInterface
{
    public function __construct(
        private readonly ResumeConfigRepositoryInterface $configs,
        private readonly ResumePdfPathResolver $paths,
        private readonly ActiveLocaleInterface $locales,
    ) {
    }

    public function paths(): array
    {
        $config = $this->configs->find();

        if ($config === null) {
            return [];
        }

        $hash = $this->paths->hashFor($config);

        return array_map(
            fn (string $locale): string => $this->paths->pathFor($locale, $hash),
            $this->locales->activeLocales(),
        );
    }
}
