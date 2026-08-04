<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Mappers;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use App\Features\Settings\Infra\Models\SettingModel;

/**
 * Converts between `settings` rows and `Setting` entities. The value type
 * is derived from the setting name (not the stored `type` column, which
 * is kept in sync for DB-level fidelity and backup exports).
 */
final class SettingMapper
{
    public function toEntity(SettingModel $model): Setting
    {
        $name = SettingName::from($model->key);

        return new Setting($name, SettingValue::fromRaw($name->type(), $model->value));
    }

    /**
     * @return array{key: string, value: string|null, type: string}
     */
    public function toRow(Setting $setting): array
    {
        return [
            'key' => $setting->name->value,
            'value' => $setting->rawValue(),
            'type' => $setting->name->type()->value,
        ];
    }
}
