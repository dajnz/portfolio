<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Exceptions\TotpLockedException;
use App\Features\Auth\Domain\Exceptions\TotpPendingInvalidException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Services\AdminAuthService;
use App\Features\Shared\App\BaseUseCase;
use Illuminate\Support\Facades\Session;

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
        private readonly AdminAuthService $auth,
    ) {
    }

    /**
     * @throws TotpPendingInvalidException when the half-auth state is invalid
     * @throws TotpLockedException         when a lockout is active (pending state cleared)
     * @throws \App\Features\Auth\Domain\Exceptions\InvalidTotpCodeException
     */
    public function verify(string $code): void
    {
        $admin = $this->repository->findById((int) Session::get('totp.pending_admin_id'));

        if ($admin === null) {
            throw new TotpPendingInvalidException();
        }

        try {
            $this->verifyTotp->verify($admin, $code);
        } catch (TotpLockedException $exception) {
            Session::forget(['totp.pending_admin_id', 'totp.pending_until']);

            throw new TotpLockedException($this->auth->totpLockoutFlash($admin));
        }

        Session::forget(['totp.pending_admin_id', 'totp.pending_until']);
        $this->login->completeLogin($admin);
    }
}
