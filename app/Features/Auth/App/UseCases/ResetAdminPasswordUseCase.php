<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\DTOs\ResetAdminPasswordDto;
use App\Features\Auth\Domain\Exceptions\AdminNotFoundException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;

/**
 * Resets an admin password and clears its TOTP lockout state, then bumps
 * the session version so every existing session is invalidated.
 */
final class ResetAdminPasswordUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $adminRepository,
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
    ) {
    }

    /**
     * @throws AdminNotFoundException when no matching admin exists
     */
    public function reset(?string $loginOrEmail, string $password): ResetAdminPasswordDto
    {
        $admin = $loginOrEmail !== null
            ? $this->adminRepository->findByLoginOrEmail($loginOrEmail)
            : $this->adminRepository->findFirst();

        if ($admin === null) {
            throw AdminNotFoundException::forLoginOrEmail($loginOrEmail);
        }

        $this->adminRepository->changePassword($admin->toId(), $password);
        $this->otpRepository->saveForAdmin($admin->toId(), $admin->toTimedOtp()->withFailuresReset());
        $this->adminRepository->incrementSessionVersion($admin->toId());

        return new ResetAdminPasswordDto($admin->toLogin());
    }
}
