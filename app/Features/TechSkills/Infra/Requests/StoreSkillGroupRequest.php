<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Requests;

use App\Features\Shared\Infra\Services\LocaleService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/skill-groups` / `PUT /admin/skill-groups/{skillGroup}`
 * (content-management.md:596-599): the non-translatable group fields
 * plus the per-locale name (required per locale per
 * content-management.md:419).
 */
final class StoreSkillGroupRequest extends FormRequest
{
    public function __construct(
        private readonly LocaleService $locales,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['integer', 'min:0'],
        ];

        foreach ($this->activeLocales() as $locale) {
            $rules["name.$locale"] = ['required', 'string', 'max:100'];
        }

        return $rules;
    }

    /**
     * Friendly attribute names for the per-locale name fields — the raw
     * `name.en` keys would otherwise leak into the validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->activeLocales() as $locale) {
            $attributes["name.$locale"] = __('admin.skills.group_name').' ['.strtoupper($locale).']';
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
