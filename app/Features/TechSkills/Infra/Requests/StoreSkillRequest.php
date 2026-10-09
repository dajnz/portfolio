<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/skills` / `PUT /admin/skills/{skill}`: the non-translatable
 * skill fields. Name uniqueness among skills is enforced case-insensitively
 * by the use case via the repository's `skillNameExists()` probe (surfaced
 * as a `name` validation error by the controller).
 */
final class StoreSkillRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'proficiency' => ['required', 'integer', 'between:1,5'],
            'skill_group_id' => ['required', 'integer', 'exists:skill_groups,id'],
            'include_in_resume' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
