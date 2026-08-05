<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Entities;

use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The admin's TOTP posture: provisioning secret, recovery codes, failure
 * counters and the escalating lockout policy (authentication.md:210-237).
 *
 * Embedded in the `Admin` aggregate; persisted through
 * `AdminTimedOtpRepositoryInterface` against the same `admins` row.
 * Lockout tiers live here (1–3 no lock / 4–6 1 min / 7–9 15 min / 10–14
 * 1 h / 15+ permanent) — the separate `TotpLockoutPolicy` service was
 * folded into this entity.
 */
final class AdminTimedOtp
{
    /** Lock "duration" for permanent lockouts (detected via isPermanentLock()). */
    public const PERMANENT_LOCK_YEARS = 100;

    /** Locks longer than a day are treated as permanent. */
    public const PERMANENT_THRESHOLD_SECONDS = 86400;

    public const RECOVERY_CODE_COUNT = 10;

    /**
     * @param  RecoveryCode[]  $recoveryCodes
     */
    private function __construct(
        private readonly ?TotpSecret $secret,
        private readonly bool $enabled,
        private readonly int $failedAttempts,
        private readonly ?DateTimeImmutable $lockedUntil,
        private readonly array $recoveryCodes,
    ) {
        if ($this->enabled && $this->secret === null) {
            throw new InvalidArgumentException('Enabled TOTP requires a provisioning secret.');
        }

        if ($this->failedAttempts < 0) {
            throw new InvalidArgumentException('TOTP failed-attempt count must not be negative.');
        }
    }

    /** TOTP not provisioned (the default state). */
    public static function disabled(): self
    {
        return new self(null, false, 0, null, []);
    }

    /**
     * @param  RecoveryCode[]  $recoveryCodes
     */
    public static function enabled(
        TotpSecret $secret,
        array $recoveryCodes,
        int $failedAttempts,
        ?DateTimeImmutable $lockedUntil,
    ): self {
        return new self($secret, true, $failedAttempts, $lockedUntil, $recoveryCodes);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function toSecret(): ?TotpSecret
    {
        return $this->secret;
    }

    public function toFailedAttempts(): int
    {
        return $this->failedAttempts;
    }

    public function toLockedUntil(): ?DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    /** @return RecoveryCode[] */
    public function toRecoveryCodes(): array
    {
        return $this->recoveryCodes;
    }

    public function toRecoveryCodesRemaining(): int
    {
        return count(array_filter($this->recoveryCodes, fn (RecoveryCode $code): bool => ! $code->isUsed()));
    }

    public function isLocked(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->lockedUntil !== null && $this->lockedUntil->getTimestamp() > $now->getTimestamp();
    }

    /**
     * Lock expiry for the given (already incremented) failed-attempt count,
     * or null for attempts 1-3 (no lock).
     */
    public static function lockExpiryFor(int $failedAttempts, ?DateTimeImmutable $now = null): ?DateTimeImmutable
    {
        $now ??= new DateTimeImmutable();

        return match (true) {
            $failedAttempts <= 3 => null,
            $failedAttempts <= 6 => $now->modify('+1 minute'),
            $failedAttempts <= 9 => $now->modify('+15 minutes'),
            $failedAttempts <= 14 => $now->modify('+1 hour'),
            default => $now->modify('+'.self::PERMANENT_LOCK_YEARS.' years'),
        };
    }

    public static function isPermanentLock(DateTimeImmutable $lockedUntil, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $lockedUntil->getTimestamp() - $now->getTimestamp() > self::PERMANENT_THRESHOLD_SECONDS;
    }

    /** Remaining lockout minutes, rounded up to the next minute (min 1). */
    public static function lockoutMinutesRemaining(DateTimeImmutable $lockedUntil, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();

        return max(1, (int) ceil(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60));
    }

    /**
     * Format rule for TOTP codes and recovery codes (content-management.md:717):
     * a 6-digit code, or a 6-64 char alphanumeric/dash string.
     */
    public static function isValidCodeFormat(string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1
            || (strlen($code) >= 6 && strlen($code) <= 64 && preg_match('/^[A-Za-z0-9-]+$/', $code) === 1);
    }

    /** Whether the input is a 6-digit TOTP code (vs. a recovery code). */
    public static function isTotpCode(string $code): bool
    {
        return preg_match('/^\d{6}$/', $code) === 1;
    }

    /** Whether the input matches an unused recovery code. */
    public function isRecoveryCodeValid(string $code): bool
    {
        return $this->findRecoveryCode($code, false) !== null;
    }

    /** Whether the input matches a consumed recovery code (B26 — counts as a failed attempt). */
    public function isRecoveryCodeUsed(string $code): bool
    {
        return $this->findRecoveryCode($code, true) !== null;
    }

    /**
     * Marks the matching unused recovery code as consumed. Throws when the
     * code does not match any unused code (invariant: only valid codes are
     * consumable).
     */
    public function withRecoveryCodeConsumed(string $code): self
    {
        $normalized = RecoveryCode::normalize($code);
        $consumed = false;

        $codes = array_map(function (RecoveryCode $recoveryCode) use ($normalized, &$consumed): RecoveryCode {
            if (! $consumed && ! $recoveryCode->isUsed() && $recoveryCode->matches($normalized)) {
                $consumed = true;

                return $recoveryCode->markUsed();
            }

            return $recoveryCode;
        }, $this->recoveryCodes);

        if (! $consumed) {
            throw new InvalidArgumentException('Recovery code does not match any unused code.');
        }

        return new self($this->secret, $this->enabled, $this->failedAttempts, $this->lockedUntil, $codes);
    }

    /** @param  RecoveryCode[]  $recoveryCodes */
    public function withRecoveryCodes(array $recoveryCodes): self
    {
        return new self($this->secret, $this->enabled, $this->failedAttempts, $this->lockedUntil, $recoveryCodes);
    }

    public function withFailedAttempts(int $failedAttempts): self
    {
        return new self($this->secret, $this->enabled, $failedAttempts, $this->lockedUntil, $this->recoveryCodes);
    }

    public function withLockedUntil(?DateTimeImmutable $lockedUntil): self
    {
        return new self($this->secret, $this->enabled, $this->failedAttempts, $lockedUntil, $this->recoveryCodes);
    }

    public function withFailuresReset(): self
    {
        return new self($this->secret, $this->enabled, 0, null, $this->recoveryCodes);
    }

    /** @param  RecoveryCode[]  $recoveryCodes */
    public function enableWith(TotpSecret $secret, array $recoveryCodes): self
    {
        return new self($secret, true, 0, null, $recoveryCodes);
    }

    public function disable(): self
    {
        return self::disabled();
    }

    /**
     * Generates 10 unique unused recovery codes (displayed once).
     *
     * @return RecoveryCode[]
     */
    public static function generateRecoveryCodes(int $count = self::RECOVERY_CODE_COUNT): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $code = RecoveryCode::generate();

            if (! in_array($code->toHash(), array_map(fn (RecoveryCode $c): string => $c->toHash(), $codes), true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function findRecoveryCode(string $code, bool $used): ?RecoveryCode
    {
        $normalized = RecoveryCode::normalize($code);

        foreach ($this->recoveryCodes as $recoveryCode) {
            if ($recoveryCode->isUsed() === $used && $recoveryCode->matches($normalized)) {
                return $recoveryCode;
            }
        }

        return null;
    }
}
