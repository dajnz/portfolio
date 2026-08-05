<?php

declare(strict_types=1);

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;

describe('AdminTimedOtp entity', function (): void {
    $now = new DateTimeImmutable('2026-08-05 12:00:00');

    function enabledOtp(?DateTimeImmutable $lockedUntil = null, array $recoveryCodes = [], int $failedAttempts = 0): AdminTimedOtp
    {
        return AdminTimedOtp::enabled(new TotpSecret('JBSWY3DPEHPK3PXP'), $recoveryCodes, $failedAttempts, $lockedUntil);
    }

    it('is disabled by default', function () use ($now): void {
        $otp = AdminTimedOtp::disabled();

        expect($otp->isEnabled())->toBeFalse()
            ->and($otp->toSecret())->toBeNull()
            ->and($otp->toFailedAttempts())->toBe(0)
            ->and($otp->toLockedUntil())->toBeNull()
            ->and($otp->toRecoveryCodes())->toBe([])
            ->and($otp->toRecoveryCodesRemaining())->toBe(0)
            ->and($otp->isLocked($now))->toBeFalse();
    });

    it('holds the enabled posture state', function (): void {
        $otp = enabledOtp();

        expect($otp->isEnabled())->toBeTrue()
            ->and($otp->toSecret()->toString())->toBe('JBSWY3DPEHPK3PXP');
    });

    it('rejects a negative failed-attempt count', function (): void {
        expect(fn () => enabledOtp(failedAttempts: -1))->toThrow(InvalidArgumentException::class);
    });

    it('reports the lockout state', function () use ($now): void {
        $locked = enabledOtp($now->modify('+5 minutes'));

        expect($locked->isLocked($now))->toBeTrue()
            ->and(enabledOtp($now->modify('-1 minute'))->isLocked($now))->toBeFalse();
    });

    it('applies the escalating lock tiers (1-3 none, 4-6 one minute)', function () use ($now): void {
        expect(AdminTimedOtp::lockExpiryFor(1, $now))->toBeNull()
            ->and(AdminTimedOtp::lockExpiryFor(3, $now))->toBeNull()
            ->and(AdminTimedOtp::lockExpiryFor(4, $now)->getTimestamp())->toBe($now->modify('+1 minute')->getTimestamp())
            ->and(AdminTimedOtp::lockExpiryFor(6, $now)->getTimestamp())->toBe($now->modify('+1 minute')->getTimestamp());
    });

    it('applies the escalating lock tiers (7-9 fifteen minutes, 10-14 one hour)', function () use ($now): void {
        expect(AdminTimedOtp::lockExpiryFor(9, $now)->getTimestamp())->toBe($now->modify('+15 minutes')->getTimestamp())
            ->and(AdminTimedOtp::lockExpiryFor(14, $now)->getTimestamp())->toBe($now->modify('+1 hour')->getTimestamp());
    });

    it('locks permanently from attempt 15', function () use ($now): void {
        $lockedUntil = AdminTimedOtp::lockExpiryFor(15, $now);

        expect(AdminTimedOtp::isPermanentLock($lockedUntil, $now))->toBeTrue();
    });

    it('computes the remaining lockout minutes rounded up', function () use ($now): void {
        $lockedUntil = $now->modify('+90 seconds');

        expect(AdminTimedOtp::lockoutMinutesRemaining($lockedUntil, $now))->toBe(2);
    });

    it('validates TOTP and recovery code formats', function (): void {
        expect(AdminTimedOtp::isValidCodeFormat('123456'))->toBeTrue()
            ->and(AdminTimedOtp::isValidCodeFormat('ABCD-EFGH-IJKL'))->toBeTrue()
            ->and(AdminTimedOtp::isValidCodeFormat('abcdefghijkl'))->toBeTrue()
            ->and(AdminTimedOtp::isValidCodeFormat('12345'))->toBeFalse()
            ->and(AdminTimedOtp::isValidCodeFormat('ABCD EFGH'))->toBeFalse()
            ->and(AdminTimedOtp::isValidCodeFormat(''))->toBeFalse()
            ->and(AdminTimedOtp::isValidCodeFormat(str_repeat('A', 65)))->toBeFalse();
    });

    it('discriminates 6-digit TOTP codes from recovery codes', function (): void {
        expect(AdminTimedOtp::isTotpCode('123456'))->toBeTrue()
            ->and(AdminTimedOtp::isTotpCode('000000'))->toBeTrue()
            ->and(AdminTimedOtp::isTotpCode('12345'))->toBeFalse()
            ->and(AdminTimedOtp::isTotpCode('1234567'))->toBeFalse()
            ->and(AdminTimedOtp::isTotpCode('ABCD-EFGH-IJKL'))->toBeFalse();
    });

    it('matches unused and consumed recovery codes', function (): void {
        $codes = AdminTimedOtp::generateRecoveryCodes(2);
        $otp = AdminTimedOtp::enabled(new TotpSecret('JBSWY3DPEHPK3PXP'), $codes, 0, null);
        $display = $codes[0]->toDisplayCode();

        expect($otp->isRecoveryCodeValid($display))->toBeTrue()
            ->and($otp->isRecoveryCodeUsed($display))->toBeFalse();

        $consumed = $otp->withRecoveryCodeConsumed($display);

        expect($consumed->isRecoveryCodeValid($display))->toBeFalse()
            ->and($consumed->isRecoveryCodeUsed($display))->toBeTrue()
            ->and($consumed->toRecoveryCodesRemaining())->toBe(1);
    });

    it('rejects consuming an unknown or already-used code', function (): void {
        $codes = AdminTimedOtp::generateRecoveryCodes(1);
        $otp = AdminTimedOtp::enabled(new TotpSecret('JBSWY3DPEHPK3PXP'), $codes, 0, null);
        $display = $codes[0]->toDisplayCode();

        expect(fn () => $otp->withRecoveryCodeConsumed('ZZZZ-ZZZZ-ZZZZ'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $otp->withRecoveryCodeConsumed($display)->withRecoveryCodeConsumed($display))->toThrow(InvalidArgumentException::class);
    });

    it('counts remaining recovery codes', function (): void {
        $codes = AdminTimedOtp::generateRecoveryCodes(3);
        $consumed = AdminTimedOtp::enabled(new TotpSecret('JBSWY3DPEHPK3PXP'), $codes, 0, null)->withRecoveryCodeConsumed($codes[0]->toDisplayCode());

        expect($consumed->toRecoveryCodesRemaining())->toBe(2);
    });

    it('mutates counters and locks via with-methods', function () use ($now): void {
        $otp = enabledOtp()
            ->withFailedAttempts(5)
            ->withLockedUntil($now->modify('+1 hour'));

        expect($otp->toFailedAttempts())->toBe(5)
            ->and($otp->toLockedUntil()->getTimestamp())->toBe($now->modify('+1 hour')->getTimestamp());

        $reset = $otp->withFailuresReset();

        expect($reset->toFailedAttempts())->toBe(0)
            ->and($reset->toLockedUntil())->toBeNull();
    });

    it('enables and disables the posture', function (): void {
        $codes = AdminTimedOtp::generateRecoveryCodes();

        $enabled = AdminTimedOtp::disabled()->enableWith(new TotpSecret('JBSWY3DPEHPK3PXP'), $codes);
        expect($enabled->isEnabled())->toBeTrue()
            ->and($enabled->toRecoveryCodesRemaining())->toBe(10)
            ->and($enabled->toFailedAttempts())->toBe(0);

        $disabled = $enabled->disable();
        expect($disabled->isEnabled())->toBeFalse()
            ->and($disabled->toSecret())->toBeNull();
    });

    it('generates 10 unique codes in XXXX-XXXX-XXXX format', function (): void {
        $codes = AdminTimedOtp::generateRecoveryCodes();

        expect($codes)->toHaveCount(10)
            ->and(array_map(fn ($c) => $c->toHash(), $codes))->toHaveCount(10);

        foreach ($codes as $code) {
            expect($code->toDisplayCode())->toMatch('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/');
        }
    });
});
