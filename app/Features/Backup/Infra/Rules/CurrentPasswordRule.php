<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use function __;

/**
 * The `current_password` rule: rejects the payload unless the submitted
 * password matches the authenticated admin's credentials. The message is
 * resolved from the auth catalog at fail time
 * (`auth.current_password_incorrect`) — the raw message key would
 * otherwise leak into the Inertia/JSON error payloads.
 */
final class CurrentPasswordRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::guard()->user();

        if ($user === null || ! Hash::check((string) $value, (string) $user->getAuthPassword())) {
            $fail(__('auth.current_password_incorrect'));
        }
    }
}
