<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;

/**
 * Toggles a single feature flag (upsert by key; cache invalidated by the
 * repository).
 */
final class ToggleFeatureFlagUseCase
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repository,
    ) {
    }

    public function toggle(FeatureFlagName $name, bool $enabled): void
    {
        $this->repository->update($name, $enabled);
    }
}
