<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * Reads a setting value by its `settings.key` name (e.g.
 * `backup_max_count`, `items_per_page`) with a caller-provided fallback —
 * the only cross-feature reuse direction allowed outside the Settings
 * feature. Implemented by `EloquentSettingsReadRepository`
 * (Shared/Infra), which delegates to the Settings feature's repository;
 * rows outside the predefined `SettingName` keys resolve to the fallback.
 */
interface SettingsReadRepositoryInterface
{
    public function value(string $key, mixed $default = null): mixed;
}
