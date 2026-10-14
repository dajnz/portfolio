<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\PasswordHasherInterface;

/**
 * Regenerates the 10 recovery codes: requires the current password,
 * replaces the stored hashed set (invalidating the old codes). Failed
 * regeneration attempts are NOT tracked by the TOTP backoff counter — this
 * is re-authentication, not a brute-force surface.
 */
final class RegenerateRecoveryCodesUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
        private readonly PasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return string[] the 10 new recovery codes (displayed once)
     *
     * @throws InvalidCurrentPasswordException
     */
    public function regenerate(Admin $admin, string $currentPassword): array
    {
        if (! $this->hasher->verify($currentPassword, $admin->toPasswordHash())) {
            throw new InvalidCurrentPasswordException();
        }

        $codes = AdminTimedOtp::generateRecoveryCodes();

        $this->otpRepository->saveForAdmin(
            $admin->toId(),
            $admin->toTimedOtp()->withRecoveryCodes($codes),
        );

        return array_map(fn (RecoveryCode $code): string => $code->toDisplayCode(), $codes);
    }
}
