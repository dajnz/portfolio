<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\App\Services\SessionVersionSynchronizer;
use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\App\Contracts\PasswordHasherInterface;
use App\Features\Shared\App\Contracts\SessionStoreInterface;

/**
 * Profile update incl. password change. The current-password check happens
 * here — the request only shapes input. On a password change the session
 * version is bumped (invalidating all other sessions) and the current
 * session is re-authenticated under the new version — the guard login
 * regenerates the session first, then `bumpAndSync()` writes the version
 * into the fresh session.
 */
final class UpdateProfileUseCase extends BaseUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly SessionVersionSynchronizer $sessionVersion,
        private readonly PasswordHasherInterface $hasher,
        private readonly SessionStoreInterface $sessions,
    ) {
    }

    /**
     * @throws InvalidCurrentPasswordException when a new password is submitted with a wrong current one
     */
    public function update(Admin $admin, string $name, string $email, string $login, ?string $newPassword, ?string $currentPassword): void
    {
        if ($newPassword !== null && ! $this->hasher->verify((string) $currentPassword, $admin->toPasswordHash())) {
            throw new InvalidCurrentPasswordException();
        }

        $this->repository->save($admin->updateCredentials($name, $email, $login));

        if ($newPassword === null) {
            return;
        }

        $this->repository->changePassword($admin->toId(), $newPassword);

        $this->sessions->forget(['totp.pending_admin_id', 'totp.pending_until']);
        $this->sessionVersion->bumpAndReauthenticate($admin->toId());
    }
}
