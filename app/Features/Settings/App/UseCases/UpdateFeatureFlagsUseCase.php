<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;

/**
 * Applies the Feature Flags tab submission (`{ flags: { key: bool, ... } }`);
 * the keys were validated against `FeatureFlagName` by the request.
 */
final class UpdateFeatureFlagsUseCase
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repository,
    ) {
    }

    /** @param array<string, bool> $flags */
    public function update(array $flags): void
    {
        foreach ($flags as $key => $enabled) {
            $this->repository->update(FeatureFlagName::from((string) $key), (bool) $enabled);
        }
    }
}
