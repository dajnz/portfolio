<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Requests;

use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /admin/experience` / `PUT /admin/experience/{item}`
 * (content-management.md:650-668, ADR-0024): the non-translatable fields
 * plus the per-locale translatable ones. `short_description` and
 * `position` are required per active locale; the company pair (name +
 * country) is optional but all-or-none per locale. `short_description`
 * is length-checked against its plain text after stripping Markdown
 * (not the raw input).
 *
 * Cross-field rules: `ended_at` must be strictly AFTER `started_at`
 * (a same-day end is invalid, ADR-0024), and `project_id` /
 * `external_project_name` are mutually exclusive. There is no
 * `is_current` flag anymore — an empty `ended_at` IS the ongoing
 * marker, and the timeline-continuity rules (overlap protection, only
 * the last item ongoing, the predecessor auto-fill) are enforced by the
 * store/update use cases. `achievements` entries carry per-locale
 * descriptions; only the locales with a description are persisted for
 * that locale's jsonb column.
 */
final class StoreExperienceRequest extends FormRequest
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
            'started_at' => ['required', 'date'],
            'ended_at' => [
                'nullable',
                'date',
                'after:started_at',
            ],
            'project_id' => [
                'nullable',
                'integer',
                'prohibited_unless:external_project_name,null',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
            ],
            'external_project_name' => [
                'nullable',
                'string',
                'max:200',
                'prohibited_unless:project_id,null',
            ],
            'tech_tags' => ['nullable', 'array'],
            'tech_tags.*' => ['integer', 'exists:skills,id'],
            'include_in_resume' => ['boolean'],
            'is_published' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'achievements' => ['nullable', 'array'],
            'achievements.*.description' => [
                'required_with:achievements',
                'array',
                function (string $attribute, mixed $value, callable $fail): void {
                    $hasDescription = false;
                    foreach ((array) $value as $description) {
                        if (is_string($description) && $description !== '') {
                            $hasDescription = true;
                            break;
                        }
                    }

                    if (! $hasDescription) {
                        $fail(__('validation.experience.achievement_description_required'));
                    }
                },
            ],
            'achievements.*.project_link' => ['nullable', 'string', 'url', 'max:500'],
        ];

        foreach ($this->activeLocales() as $locale) {
            $companyRule = $this->companyPairRule($locale);

            $rules["position.$locale"] = ['required', 'string', 'max:200'];
            $rules["company_name.$locale"] = ['nullable', 'string', 'max:200', $companyRule];
            $rules["company_country.$locale"] = ['nullable', 'string', 'max:100', $companyRule];
            $rules["short_description.$locale"] = [
                'required',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    $plainLength = mb_strlen($this->stripMarkdown((string) $value));

                    if ($plainLength > 500) {
                        $fail(__('validation.experience.short_description_plain_length', [
                            'attribute' => $this->attributes()[$attribute] ?? $attribute,
                            'max' => 500,
                        ]));
                    }
                },
            ];
            $rules["achievements.*.description.$locale"] = [
                'nullable',
                'string',
                'max:1000',
            ];
        }

        return $rules;
    }

    /**
     * The company pair (company name + country) is all-or-none per
     * locale (ADR-0024 — position is mandatory on its own; the pair is
     * optional, but a lone company name or a lone country is
     * meaningless).
     *
     * @return callable(string, mixed, callable): void
     */
    private function companyPairRule(string $locale): callable
    {
        return function (string $attribute, mixed $value, callable $fail) use ($locale): void {
            $set = 0;
            foreach (['company_name', 'company_country'] as $field) {
                $fieldValue = $this->input("{$field}.{$locale}");
                if (is_string($fieldValue) && $fieldValue !== '') {
                    $set++;
                }
            }

            if ($set === 1) {
                $fail(__('validation.experience.company_fields_all_or_none', [
                    'attribute' => $this->attributes()[$attribute] ?? $attribute,
                ]));
            }
        };
    }

    /**
     * Friendly attribute names for the per-locale fields — the raw
     * `position.en` keys would otherwise leak into the validation
     * messages; the label is localized and the locale suffix makes it
     * clear which translation is at fault.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->activeLocales() as $locale) {
            $suffix = ' ['.strtoupper($locale).']';
            $attributes["position.$locale"] = __('admin.experience.position').$suffix;
            $attributes["company_name.$locale"] = __('admin.experience.company_name').$suffix;
            $attributes["company_country.$locale"] = __('admin.experience.country').$suffix;
            $attributes["short_description.$locale"] = __('admin.experience.short_description').$suffix;
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

    /**
     * Coarse Markdown → plain-text strip: removes code, image/link
     * syntax (keeping their text), heading/list/quote markers and
     * emphasis, collapsing the remainder to a single whitespace run.
     * Only a length check depends on it — a deliberately simple
     * approximation of the renderer's output.
     */
    private function stripMarkdown(string $markdown): string
    {
        $text = preg_replace('/```[\s\S]*?```/', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/`[^`]*`/', ' ', $text) ?? $text;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}(?:[-*_]\s*){3,}\s*$/m', ' ', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}[>*+-]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/[*_~]{1,3}([^*_~]+)[*_~]{1,3}/', '$1', $text) ?? $text;

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
