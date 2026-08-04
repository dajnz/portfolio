<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\RepositoryInterfaces;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;

interface SettingRepositoryInterface
{
    /** @return Setting[] */
    public function findAll(): array;

    /** Cached read (1-minute TTL); rows outside `SettingName` are never returned. */
    public function find(SettingName $name): ?Setting;

    /** Persists the setting and invalidates its cache entry. */
    public function save(Setting $setting): void;

    /** Builds a type-conformant setting from the name + raw value and saves it. */
    public function saveValue(SettingName $name, mixed $value): void;

    /** Typed value of the setting, or the caller-provided fallback when absent. */
    public function value(SettingName $name, mixed $default = null): mixed;
}
