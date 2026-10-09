<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /admin/resume` — the full resume configuration payload: mode, the
 * layout JSON (template + the five sections' visibility/order), the
 * manual file path (required in manual mode) and the per-locale About Me
 * texts (nullable — an empty text is stored as null and falls back to
 * the about short pitch in the PDF).
 */
final class UpdateResumeConfigRequest extends FormRequest
{
    private const TEMPLATES = ['modern', 'classic', 'compact'];

    private const SECTION_KEYS = ['header', 'about_me', 'skills', 'experience', 'contacts'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'mode' => ['required', 'string', Rule::in(['auto', 'manual'])],
            'layout' => ['required', 'array'],
            'layout.template' => ['required', 'string', Rule::in(self::TEMPLATES)],
            'layout.sections' => ['required', 'array'],
            'custom_resume_file' => [
                'nullable',
                'string',
                'max:255',
                'required_if:mode,manual',
            ],
            'about_text' => ['nullable', 'array'],
            'about_text.*' => ['nullable', 'string', 'max:1000'],
        ];

        foreach (self::SECTION_KEYS as $key) {
            $rules["layout.sections.{$key}"] = ['required', 'array'];
            $rules["layout.sections.{$key}.visible"] = ['required', 'boolean'];
            $rules["layout.sections.{$key}.order"] = ['required', 'integer', 'between:0,4'];
        }

        return $rules;
    }
}
