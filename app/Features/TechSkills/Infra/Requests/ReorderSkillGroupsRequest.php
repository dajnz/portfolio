<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/skill-groups/reorder` — the ADR-0005 payload `{ "ids": [...] }`:
 * group IDs in the new drag order; the zero-indexed array position
 * becomes `sort_order`.
 */
final class ReorderSkillGroupsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
