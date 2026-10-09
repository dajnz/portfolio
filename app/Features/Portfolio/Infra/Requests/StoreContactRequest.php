<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Requests;

use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/contacts`: the non-translatable contact fields plus the
 * per-locale display name (required per locale).
 */
final class StoreContactRequest extends FormRequest
{
    public function __construct(
        private readonly ActiveLocaleService $locales,
    ) {
        parent::__construct();
    }

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
     * Friendly attribute names for the per-locale name fields (the raw
     * `name.en` keys would otherwise leak into validation messages).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->activeLocales() as $locale) {
            $attributes["name.$locale"] = __('admin.contacts.name').' ['.strtoupper($locale).']';
        }

        return $attributes;
    }

    /**
     * @return string[]
     */
    private function activeLocales(): array
    {
        return $this->locales->activeLocales();
    }
}
