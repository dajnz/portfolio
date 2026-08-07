<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/contacts/reorder` — the A4 payload `{ "ids": [...] }`:
 * contact IDs in the new drag order; the zero-indexed array position
 * becomes `sort_order`.
 */
final class ReorderContactsRequest extends FormRequest
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
