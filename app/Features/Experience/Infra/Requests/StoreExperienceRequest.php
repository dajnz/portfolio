<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Requests;

use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /admin/experience` / `PUT /admin/experience/{item}`: the
 * non-translatable fields plus the per-locale translatable ones.
 * `short_description` and `position` are required per active locale; the
 * company pair (name + country) is optional but all-or-none per locale.
 * `short_description` is length-checked against its plain text after
 * stripping Markdown (not the raw input).
 *
 * Cross-field rules: `ended_at` must be strictly AFTER `started_at`
 * (a same-day end is invalid), and `project_id` / `external_project_name`
 * are mutually exclusive. An empty `ended_at` IS the ongoing marker, and
 * the timeline-continuity rules (overlap protection, only the last item
 * ongoing, the predecessor auto-fill) are enforced by the store/update use
 * cases. `achievements` entries carry per-locale descriptions; only the
 * locales with a description are persisted for that locale's jsonb column.
 */
final class StoreExperienceRequest extends FormRequest
{
    public function __construct(
        private readonly ActiveLocaleService $locales,
        private readonly MarkdownContentPipelineInterface $markdown,
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
            'external_project_url' => [
                'nullable',
                'string',
                'url',
                'max:500',
                'prohibited_unless:project_id,null',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (is_string($value) && $value !== '' && $this->input('external_project_name') === null) {
                        $fail(__('validation.experience.external_project_url_requires_project', [
                            'attribute' => $this->attributes()[$attribute] ?? $attribute,
                        ]));
                    }
                },
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
                    $plainLength = mb_strlen($this->markdown->plainText((string) $value));

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
     * The company pair (company name + country) is all-or-none per locale
     * (position is mandatory on its own; the pair is optional, but a lone
     * company name or a lone country is meaningless).
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
        $attributes = [
            'started_at' => __('admin.experience.started_at'),
            'ended_at' => __('admin.experience.ended_at'),
            'project_id' => __('admin.experience.project'),
            'external_project_name' => __('admin.experience.project'),
            'external_project_url' => __('admin.experience.external_project_url'),
            'tech_tags' => __('admin.experience.tech_tags'),
            'tech_tags.*' => __('admin.experience.tech_tags'),
            'sort_order' => __('admin.experience.sort_order'),
            'include_in_resume' => __('admin.experience.include_in_resume'),
            'is_published' => __('admin.experience.published'),
            'achievements' => __('admin.experience.achievements'),
            'achievements.*.description' => __('admin.experience.achievement_description'),
        ];

        foreach ($this->activeLocales() as $locale) {
            $suffix = ' ['.strtoupper($locale).']';
            $attributes["position.$locale"] = __('admin.experience.position').$suffix;
            $attributes["company_name.$locale"] = __('admin.experience.company_name').$suffix;
            $attributes["company_country.$locale"] = __('admin.experience.country').$suffix;
            $attributes["short_description.$locale"] = __('admin.experience.short_description').$suffix;
            $attributes["achievements.*.description.$locale"] = __('admin.experience.achievement_description').$suffix;
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
