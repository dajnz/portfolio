<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Repositories;

use App\Features\Settings\Domain\Entities\FeatureFlag;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Infra\Mappers\FeatureFlagMapper;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use Illuminate\Support\Facades\Cache;

/**
 * Feature flags are cached separately from settings with the same 1-minute
 * TTL (architecture.md). Reads through `isEnabled()` hit the cache; any
 * write invalidates the affected flag.
 */
final class EloquentFeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(
        private readonly FeatureFlagMapper $mapper,
    ) {
    }

    public function findAll(): array
    {
        return FeatureFlagModel::query()
            ->orderBy('key')
            ->get()
            ->map(fn (FeatureFlagModel $row): FeatureFlag => $this->mapper->toEntity($row))
            ->all();
    }

    public function find(FeatureFlagName $name): ?FeatureFlag
    {
        $row = FeatureFlagModel::query()->find($name->value);

        return $row === null ? null : $this->mapper->toEntity($row);
    }

    public function isEnabled(FeatureFlagName $name): bool
    {
        return Cache::remember(
            $this->cacheKey($name),
            self::CACHE_TTL_SECONDS,
            fn (): bool => $this->find($name)?->toEnabled() ?? false,
        );
    }

    public function update(FeatureFlagName $name, bool $enabled): void
    {
        FeatureFlagModel::query()->updateOrCreate(
            ['key' => $name->value],
            ['enabled' => $enabled],
        );

        Cache::forget($this->cacheKey($name));
    }

    private function cacheKey(FeatureFlagName $name): string
    {
        return 'feature_flag:'.$name->value;
    }
}
