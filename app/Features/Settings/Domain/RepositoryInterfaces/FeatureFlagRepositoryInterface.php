<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\RepositoryInterfaces;

use App\Features\Settings\Domain\Entities\FeatureFlag;
use App\Features\Settings\Domain\Enums\FeatureFlagName;

interface FeatureFlagRepositoryInterface
{
    /** @return FeatureFlag[] */
    public function findAll(): array;

    public function find(FeatureFlagName $name): ?FeatureFlag;

    /** Cached per-flag read (1-minute TTL), used by the feature middleware. */
    public function isEnabled(FeatureFlagName $name): bool;

    /** Upserts the flag state and invalidates the per-flag cache. */
    public function update(FeatureFlagName $name, bool $enabled): void;
}
