<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /admin/blog/{article}/skills/{skill}` (content-management.md:
 * 253): validates that the skill exists. The duplicate-attachment 409
 * is thrown by the repository.
 */
final class AttachArticleSkillRequest extends FormRequest
{
    /**
     * The skill is a ROUTE parameter — merged into the validation
     * data (FormRequest validation covers the request input only).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'skill' => $this->route('skill'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skill' => ['required', 'integer', Rule::exists('skills', 'id')],
        ];
    }
}
