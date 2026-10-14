<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\App\Contracts\PasswordHasherInterface;
use App\Features\Shared\App\Contracts\AdminSessionInterface;
use DateTimeImmutable;

/**
 * Password login flow: credential verification, the half-auth TOTP state
 * (`totp.pending_*`, 5-minute expiry), and full session authentication.
 * The guard login is never reached before TOTP verification succeeds.
 */
final class LoginUseCase extends BaseUseCase
{
    private const TOTP_PENDING_TTL_SECONDS = 300;

    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly AdminSessionInterface $sessions,
        private readonly PasswordHasherInterface $hasher,
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
        $this->sessions->put([
            'totp.pending_admin_id' => $admin->toId(),
            'totp.pending_until' => (new DateTimeImmutable())
                ->modify('+'.self::TOTP_PENDING_TTL_SECONDS.' seconds')
                ->getTimestamp(),
        ]);
    }

    /** Completes authentication: session login + version + ID regeneration. */
    public function completeLogin(Admin $admin, string $ip, bool $remember = false): void
    {
        $this->sessions->loginUsingId($admin->toId(), $remember);
        $this->sessions->put(['session_version' => $admin->toSessionVersion()]);

        $this->logger->info('Successful login', [
            'ip' => $ip,
            'admin_id' => $admin->toId(),
        ]);
    }
}
