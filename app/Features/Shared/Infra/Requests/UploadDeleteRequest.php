<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `DELETE /api/upload` validation (routes.md:360) — JSON body with a single
 * `path` field (`required|string|max:255`). The controller reads the value
 * via `$request->json()->get('path')`: the route sits in the `api` group,
 * which does not parse form-encoded bodies by default.
 */
final class UploadDeleteRequest extends FormRequest
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
            'path' => ['required', 'string', 'max:255'],
        ];
    }
}
