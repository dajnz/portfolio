<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/profile/totp-enable` — dual-purpose enrollment endpoint.
 * Without `code` it starts enrollment; with `code` it verifies and enables.
 * The code is checked against the pending secret in `EnableTotpUseCase`.
 */
final class TotpEnableRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:6'],
        ];
    }
}
