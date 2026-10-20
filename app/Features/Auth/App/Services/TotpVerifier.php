<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Services;

use App\Features\Auth\App\Contracts\TotpServiceInterface;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException;
use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use DateTimeImmutable;

/**
 * TOTP + recovery-code verification with the per-account escalating
 * backoff. Failed attempts atomically increment `totp_failed_attempts`;
 * the lock tiers are evaluated from the resulting count by
 * `AdminTimedOtp::lockExpiryFor()`. Recovery-code misses count as failed
 * TOTP attempts.
 *
 * Shared by the login-TOTP and GitHub-link-TOTP flows.
 */
final class TotpVerifier
{
    public function __construct(
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
        private readonly TotpServiceInterface $totpService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Verifies a 6-digit TOTP code (within the ±1 window) or a recovery
     * code. On success the failure counters are reset; a consumed recovery
     * code is marked used.
     *
     * @throws TotpLockedException       when a lockout is active
     * @throws InvalidTotpCodeException  when the code is wrong
     */
    public function verify(Admin $admin, string $code, string $ip): void
    {
        $now = new DateTimeImmutable();
        $otp = $admin->toTimedOtp();

        if ($otp->isLocked($now)) {
            throw new TotpLockedException();
        }

        $verified = false;

        if (AdminTimedOtp::isTotpCode($code)) {
            $secret = $otp->toSecret()?->toString();
            $verified = $secret !== null && $this->totpService->verify($secret, $code);
        } else {
            $verified = $otp->isRecoveryCodeValid($code);

            if ($verified) {
                $otp = $otp->withRecoveryCodeConsumed($code);
            }
        }

        if (! $verified) {
            $this->registerFailure($admin->toId(), $admin->toTimedOtp(), $now, $ip);

            throw new InvalidTotpCodeException();
        }

        $this->otpRepository->saveForAdmin($admin->toId(), $otp->withFailuresReset());
    }

    private function registerFailure(int $adminId, AdminTimedOtp $otp, DateTimeImmutable $now, string $ip): void
    {
        $attempts = $this->otpRepository->incrementFailedAttempts($adminId);
        $lockedUntil = AdminTimedOtp::lockExpiryFor($attempts, $now);

        $this->otpRepository->saveForAdmin(
            $adminId,
            $otp->withFailedAttempts($attempts)->withLockedUntil($lockedUntil),
        );

        $context = [
            'ip' => $ip,
            'admin_id' => $adminId,
            'attempt_count' => $attempts,
        ];

        if ($attempts >= 7) {
            $duration = $lockedUntil === null
                ? 'none'
                : (AdminTimedOtp::isPermanentLock($lockedUntil, $now)
                    ? 'permanent'
                    : max(1, (int) round(($lockedUntil->getTimestamp() - $now->getTimestamp()) / 60)).' minutes');

            $context['lock_duration'] = $duration;

            $this->logger->error('TOTP lockout escalated (attempts '.$attempts.')', $context);
        } else {
            $this->logger->warning('TOTP failure (attempts '.$attempts.')', $context);
        }
    }
}
