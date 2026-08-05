<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PUT /admin/profile` (content-management.md:719-727). Shape-only
 * validation — the current-password verification happens in
 * `UpdateProfileUseCase` (the domain owns it).
 */
final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->user()?->getAuthIdentifier();

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($id)],
            'login' => ['required', 'string', 'max:100', Rule::unique('admins', 'login')->ignore($id)],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ];
    }
}
