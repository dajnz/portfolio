<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-authentication payload shared by `POST /admin/profile/totp-disable`
 * and `POST /admin/profile/totp-recovery-codes` (authentication.md:203,239).
 * The password is verified against the hash in the corresponding use case.
 */
final class RequireCurrentPasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }
}
