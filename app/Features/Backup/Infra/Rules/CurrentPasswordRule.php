<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * The `current_password` rule (content-management.md:742,755,
 * backup.md:590): rejects the payload unless the submitted password
 * matches the authenticated admin's credentials. The message comes
 * from the auth catalog (`auth.current_password_incorrect`).
 */
final class CurrentPasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::guard()->user();

        if ($user === null || ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
            $fail('auth.current_password_incorrect');
        }
    }
}
