<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Requests;

use App\Features\Settings\Domain\Entities\FeatureFlag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `{ flags: { key: bool, ... } }` — keys must be known `FeatureFlagName`s;
 * values must be booleans.
 */
final class UpdateFeatureFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'flags' => ['required', 'array'],
            'flags.*' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach (array_keys($this->input('flags', [])) as $key) {
                if (! FeatureFlag::isValidName((string) $key)) {
                    $validator->errors()->add("flags.$key", "The selected flags.$key is invalid.");
                }
            }
        });
    }
}
