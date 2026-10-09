<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/projects/{project}/tags`: `{ skill_id: number }` — the
 * skill must exist; a duplicate attachment surfaces as a 409 via the
 * domain exception.
 */
final class AttachProjectTagRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
        ];
    }
}
