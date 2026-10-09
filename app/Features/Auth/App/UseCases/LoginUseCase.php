<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Password login flow: credential verification, the half-auth TOTP state
 * (`totp.pending_*`, 5-minute expiry), and full session authentication.
 * `Auth::login()` is never called before TOTP verification succeeds.
 */
final class LoginUseCase extends BaseUseCase
{
    private const TOTP_PENDING_TTL_SECONDS = 300;

    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function attempt(string $loginOrEmail, string $password): ?Admin
    {
        $admin = $this->repository->findByLoginOrEmail($loginOrEmail);

        if ($admin === null || ! Hash::check($password, $admin->toPasswordHash())) {
            $this->logger->info('Failed login attempt', [
                'ip' => request()->ip(),
                'login_or_email' => $loginOrEmail,
            ]);

            return null;
        }

        return $admin;
    }

    /** Enters the half-auth state for the admin (5-minute expiry). */
    public function beginTotpVerification(Admin $admin): void
    {
        session([
            'totp.pending_admin_id' => $admin->toId(),
            'totp.pending_until' => now()->addSeconds(self::TOTP_PENDING_TTL_SECONDS)->getTimestamp(),
        ]);
    }

    /** Completes authentication: session login + version + ID regeneration. */
    public function completeLogin(Admin $admin, bool $remember = false): void
    {
        Auth::loginUsingId($admin->toId(), $remember);
        session(['session_version' => $admin->toSessionVersion()]);

        $this->logger->info('Successful login', [
            'ip' => request()->ip(),
            'admin_id' => $admin->toId(),
        ]);
    }
}
