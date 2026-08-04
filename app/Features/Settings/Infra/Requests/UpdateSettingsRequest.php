<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Requests;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * `PUT /admin/settings` — grouped `{ settings: { key: value, ... } }`
 * payload for the active tab.
 *
 * Per-key validation is the domain's job: type conformance is enforced by
 * `SettingValue`, and the name-specific value domains (bounds, report
 * frequency) by the `Setting` entity. This request only checks that the
 * key is known and persisted (`exists:settings,key` per the spec), then
 * constructs the domain objects and surfaces their failures:
 *  - type conformance → "Invalid value type for '{key}'." (spec message);
 *  - domain constraints → the entity's own message.
 */
final class UpdateSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->settingsKeys() as $key) {
                $name = SettingName::tryFrom($key);

                if ($name === null || ! SettingModel::query()->whereKey($key)->exists()) {
                    $validator->errors()->add("settings.$key", "The selected settings.$key is invalid.");

                    continue;
                }

                try {
                    $settingValue = new SettingValue($name->type(), $this->input("settings.$key"));
                } catch (InvalidArgumentException) {
                    $validator->errors()->add("settings.$key", "Invalid value type for '$key'.");

                    continue;
                }

                try {
                    new Setting($name, $settingValue);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add("settings.$key", $exception->getMessage());
                }
            }
        });
    }

    /**
     * @return string[]
     */
    private function settingsKeys(): array
    {
        return array_keys($this->input('settings', []));
    }
}
