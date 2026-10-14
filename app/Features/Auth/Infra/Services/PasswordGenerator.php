<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Generates random 24-char admin passwords that satisfy the password rules
 * (12+ chars, mixed case, digits) and are not known-compromised. The
 * `uncompromised()` check queries the HaveIBeenPwned API — retried up to
 * 10 times on failure.
 */
final class PasswordGenerator
{
    private const MAX_ATTEMPTS = 10;

    public function generate(): string
    {
        $rule = Password::min(12)->mixedCase()->numbers()->uncompromised();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $password = Str::random(24);

            if (Validator::make(['password' => $password], ['password' => $rule])->passes()) {
                return $password;
            }
        }

        throw new RuntimeException('Unable to generate a non-compromised password after 10 attempts — check network connectivity to the HaveIBeenPwned API');
    }
}
