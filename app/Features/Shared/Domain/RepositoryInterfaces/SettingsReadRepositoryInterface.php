<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * The cross-feature settings surface (ADR-0018): reads a setting value by
 * its `settings.key` name (e.g. `backup_max_count`, `items_per_page`)
 * with a caller-provided fallback — the only cross-feature reuse
 * direction allowed outside the Settings feature (per the
 * "cross-feature reuse only via Shared" rule). Implemented by
 * `EloquentSettingsReadRepository` (Shared/Infra), which delegates to
 * the Settings feature's repository — rows outside the predefined
 * `SettingName` keys resolve to the fallback.
 */
interface SettingsReadRepositoryInterface
{
    public function value(string $key, mixed $default = null): mixed;
}
