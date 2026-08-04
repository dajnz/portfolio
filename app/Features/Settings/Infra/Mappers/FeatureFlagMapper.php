<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Mappers;

use App\Features\Settings\Domain\Entities\FeatureFlag;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;

final class FeatureFlagMapper
{
    public function toEntity(FeatureFlagModel $model): FeatureFlag
    {
        return new FeatureFlag(
            name: FeatureFlagName::from($model->key),
            enabled: (bool) $model->enabled,
            description: $model->description,
        );
    }
}
