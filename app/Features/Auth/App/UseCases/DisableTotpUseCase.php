<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\App\Services\SessionVersionSynchronizer;
use App\Features\Shared\App\BaseUseCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

/**
 * Disables TOTP: requires the current password, removes the secret +
 * recovery codes, and bumps the session version so all existing sessions
 * (authenticated under the old 2FA posture) must re-authenticate. The
 * current session is re-authenticated first (session ID regeneration),
 * then the new version is written into the fresh session.
 */
final class DisableTotpUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminTimedOtpRepositoryInterface $otpRepository,
        private readonly SessionVersionSynchronizer $sessionVersion,
    ) {
    }

    /**
     * @throws InvalidCurrentPasswordException
     */
    public function disable(Admin $admin, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $admin->toPasswordHash())) {
            throw new InvalidCurrentPasswordException();
        }

        $this->otpRepository->saveForAdmin($admin->toId(), $admin->toTimedOtp()->disable());

        Session::forget(['totp.pending_admin_id', 'totp.pending_until']);
        $this->sessionVersion->bumpAndReauthenticate($admin->toId());
    }
}
