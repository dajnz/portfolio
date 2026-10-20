<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\Services\LoginSessionService;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\App\Contracts\PasswordHasherInterface;

/**
 * Password login flow: credential verification, then the half-auth TOTP
 * state and full session authentication through `LoginSessionService`.
 * The guard login is never reached before TOTP verification succeeds.
 */
final class LoginUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly PasswordHasherInterface $hasher,
        private readonly LoginSessionService $loginSession,
    ) {
    }

    public function attempt(string $loginOrEmail, string $password, string $ip): ?Admin
    {
        $admin = $this->repository->findByLoginOrEmail($loginOrEmail);

        if ($admin === null || ! $this->hasher->verify($password, $admin->toPasswordHash())) {
            $this->logger->info('Failed login attempt', [
                'ip' => $ip,
                'login_or_email' => $loginOrEmail,
            ]);

            return null;
        }

        return $admin;
    }

    /** Enters the half-auth state for the admin (5-minute expiry). */
    public function beginTotpVerification(Admin $admin): void
    {
        $this->loginSession->beginTotpVerification($admin);
    }

    /** Completes authentication: session login + version + ID regeneration. */
    public function completeLogin(Admin $admin, string $ip, bool $remember = false): void
    {
        $this->loginSession->completeLogin($admin, $ip, $remember);
    }
}
