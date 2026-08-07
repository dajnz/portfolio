<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Requests;

use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /admin/about` (content-management.md:572-586): the non-translatable
 * fields plus per-locale translatable fields. Fields marked "required
 * per locale" are required only for the locales active in the
 * `supported_locales` setting; rows of disabled locales are preserved,
 * not deleted (content-management.md:419).
 */
final class UpdateAboutRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'photo' => ['nullable', 'string', 'max:255'],
            'photo_alt' => ['nullable', 'array'],
            'show_location' => ['boolean'],
            'timezone' => ['required', 'string', 'timezone'],
            'relocation_readiness' => ['boolean'],
            'availability_status' => ['required', 'string', Rule::in(array_column(AvailabilityStatus::cases(), 'value'))],
            'work_type' => ['required', 'string', Rule::in(['remote', 'hybrid', 'office'])],
        ];

        foreach ($this->activeLocales() as $locale) {
            $rules["name.$locale"] = ['required', 'string', 'max:255'];
            $rules["title.$locale"] = ['required', 'string', 'max:200'];
            $rules["short_pitch.$locale"] = ['required', 'string', 'max:500'];
            $rules["location.$locale"] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * @return string[]
     */
    private function activeLocales(): array
    {
        $locales = app(SettingRepositoryInterface::class)
            ->value(SettingName::SupportedLocales, config('portfolio.supported_locales', ['en', 'ru']));

        return array_values(array_unique(array_map('strval', (array) $locales)));
    }
}
