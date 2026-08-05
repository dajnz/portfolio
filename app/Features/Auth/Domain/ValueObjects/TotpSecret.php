<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable TOTP provisioning secret (base32, unpadded).
 *
 * Secrets are always generated server-side via `TotpService::generateSecret()`
 * and verified against this format on construction, so every instance is
 * valid by construction.
 */
final class TotpSecret
{
    public function __construct(private readonly string $value)
    {
        if (preg_match('/^[A-Z2-7]{16,64}$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid TOTP secret: expected unpadded base32 (A-Z2-7), 16-64 chars.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
