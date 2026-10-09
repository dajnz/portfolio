<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Rules;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts either a 6-digit TOTP code or a recovery code up to 64 chars.
 * The format rule itself lives on the `AdminTimedOtp` entity — this rule is
 * only the request-validation entry.
 */
final class TotpOrRecoveryCodeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! AdminTimedOtp::isValidCodeFormat((string) $value)) {
            $fail('auth.code_format_invalid');
        }
    }
}
