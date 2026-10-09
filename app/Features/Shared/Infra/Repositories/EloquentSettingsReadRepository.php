<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;

/**
 * The cross-feature settings reader: delegates to the Settings feature's
 * repository — the same 1-minute cached reads — resolving the string key against
 * the predefined `SettingName` enum. Unknown keys (not a predefined setting)
 * resolve to the caller's fallback; the shared surface exists so non-Settings
 * features never import the Settings feature directly.
 */
final class EloquentSettingsReadRepository implements SettingsReadRepositoryInterface
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $name = SettingName::tryFrom($key);

        if ($name === null) {
            return $default;
        }

        return $this->settings->value($name, $default);
    }
}
