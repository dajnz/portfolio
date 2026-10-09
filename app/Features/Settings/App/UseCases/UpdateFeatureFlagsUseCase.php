<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Settings\Domain\Enums\FeatureFlagName;

/**
 * Applies the Feature Flags tab submission (`{ flags: { key: bool, ... } }`);
 * the keys were validated against `FeatureFlagName` by the request.
 */
final class UpdateFeatureFlagsUseCase
{
    public function __construct(
        private readonly ToggleFeatureFlagUseCase $toggle,
    ) {
    }

    /** @param array<string, bool> $flags */
    public function update(array $flags): void
    {
        foreach ($flags as $key => $enabled) {
            $this->toggle->toggle(FeatureFlagName::from((string) $key), (bool) $enabled);
        }
    }
}
