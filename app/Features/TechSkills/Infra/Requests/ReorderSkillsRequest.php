<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/skills/reorder` — the ADR-0005 skills payload
 * `{ "skills": [{ "id", "skill_group_id" }] }`: skill IDs in the new
 * drag order, each carrying its (possibly new) group. The zero-indexed
 * array position becomes `sort_order` and the group assignment is
 * persisted — this is what makes cross-group drag-and-drop moves stick.
 */
final class ReorderSkillsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skills' => ['required', 'array'],
            'skills.*.id' => ['required', 'integer', 'min:1'],
            'skills.*.skill_group_id' => ['required', 'integer', 'exists:skill_groups,id'],
        ];
    }
}
