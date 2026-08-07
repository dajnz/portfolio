<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Requests;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/contacts` (content-management.md:607-616): the
 * non-translatable contact fields plus the per-locale display name
 * (required per locale per content-management.md:419).
 */
final class StoreContactRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'value' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'string', 'url', 'max:500'],
            'include_in_resume' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];

        foreach ($this->activeLocales() as $locale) {
            $rules["name.$locale"] = ['required', 'string', 'max:100'];
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
