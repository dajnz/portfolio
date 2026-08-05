<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared `{"ids": [...]}` payload for the admin bulk-action routes
 * (architecture.md:794, routes.md:97-128). Existence against the target
 * table is feature-specific and validated by the consuming form requests;
 * this base only enforces the payload shape.
 */
final class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ];
    }
}
