<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Repositories;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use App\Features\Settings\Infra\Mappers\SettingMapper;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent-backed settings repository. Owns the settings cache: values are
 * cached in Redis for an hour under `setting:v2:{key}`; writes invalidate the
 * affected key. Missing rows are never cached so the caller's config()
 * fallback cannot go stale.
 *
 * The cache stores the typed value (plain data) wrapped in an array, not the
 * entity: the framework's cache unserialize whitelist
 * (`cache.serializable_classes`) forbids arbitrary objects, and a bare null
 * value reads back as a miss (`Cache::has` rejects nulls), which re-queried
 * null-valued settings on every request.
 */
final class EloquentSettingRepository implements SettingRepositoryInterface
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly SettingMapper $mapper,
    ) {
    }

    public function findAll(): array
    {
        return SettingModel::query()
            ->whereIn('key', array_column(SettingName::cases(), 'value'))
            ->orderBy('key')
            ->get()
            ->map(fn (SettingModel $row): Setting => $this->mapper->toEntity($row))
            ->all();
    }

    public function find(SettingName $name): ?Setting
    {
        $cacheKey = $this->cacheKey($name);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && array_key_exists('value', $cached)) {
            return new Setting($name, new SettingValue($name->type(), $cached['value']));
        }

        $row = SettingModel::query()->find($name->value);
        $setting = $row === null ? null : $this->mapper->toEntity($row);

        if ($setting !== null) {
            Cache::put($cacheKey, ['value' => $setting->value()], self::CACHE_TTL_SECONDS);
        }

        return $setting;
    }

    public function save(Setting $setting): void
    {
        SettingModel::query()->updateOrCreate(
            ['key' => $setting->toName()],
            $this->mapper->toRow($setting),
        );

        Cache::forget($this->cacheKey(SettingName::from($setting->toName())));
    }

    public function saveValue(SettingName $name, mixed $value): void
    {
        $this->save(new Setting($name, new SettingValue($name->type(), $value)));
    }

    public function value(SettingName $name, mixed $default = null): mixed
    {
        return $this->find($name)?->value() ?? $default;
    }

    private function cacheKey(SettingName $name): string
    {
        return 'setting:v2:'.$name->value;
    }
}
