<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\Contracts\TotpLockoutMessengerInterface;
use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\Exceptions\TotpPendingInvalidException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\AdminSessionInterface;

/**
 * Login-flow TOTP verification: resolves the half-auth admin from the
 * session, verifies the code (backoff applied by `VerifyTotpUseCase`),
 * clears `totp.pending_*` and completes the login. A lockout clears the
 * pending state and rethrows with the localized lockout flash message.
 */
final class VerifyLoginTotpUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly VerifyTotpUseCase $verifyTotp,
        private readonly LoginUseCase $login,
        private readonly TotpLockoutMessengerInterface $lockoutMessages,
        private readonly AdminSessionInterface $sessions,
    ) {
    }

    /**
     * @throws TotpPendingInvalidException when the half-auth state is invalid
     * @throws TotpLockedException         when a lockout is active (pending state cleared)
     * @throws \App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException
     */
    public function verify(string $code, string $ip): void
    {
        $admin = $this->repository->findById((int) $this->sessions->get('totp.pending_admin_id'));

        if ($admin === null) {
            throw new TotpPendingInvalidException();
        }

        try {
            $this->verifyTotp->verify($admin, $code, $ip);
        } catch (TotpLockedException $exception) {
            $this->sessions->forget(['totp.pending_admin_id', 'totp.pending_until']);

            throw new TotpLockedException($this->lockoutMessages->totpLockoutFlash($admin));
        }

        $this->sessions->forget(['totp.pending_admin_id', 'totp.pending_until']);
        $this->login->completeLogin($admin, $ip);
    }
}
