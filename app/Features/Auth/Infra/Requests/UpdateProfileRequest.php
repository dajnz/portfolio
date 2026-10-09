<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * `PUT /admin/profile`. Shape-only validation — the current-password
 * verification happens in `UpdateProfileUseCase` (the domain owns it).
 */
final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->user()?->getAuthIdentifier();

        // The password fields may be omitted entirely (profile-only update
        // from the info form). When ANY of them is present, the request
        // intends a password change and all of them become required —
        // empty strings arrive as `null` via ConvertEmptyStringsToNull and
        // would otherwise slip through `nullable`, silently succeeding
        // without changing anything.
        $changingPassword = $this->exists('password') || $this->exists('current_password');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($id)],
            'login' => ['required', 'string', 'max:100', Rule::unique('admins', 'login')->ignore($id)],
            'current_password' => [$changingPassword ? 'required' : 'nullable', 'string'],
            'password' => [
                $changingPassword ? 'required' : 'nullable',
                'string',
                // Password rules: 12+ chars, mixed case, at least one digit.
                // `uncompromised()` stays with the generator
                // (admin:create/reset) — the request path must not depend on
                // the external HaveIBeenPwned API.
                Password::min(12)->mixedCase()->numbers(),
                'confirmed',
            ],
        ];
    }
}
