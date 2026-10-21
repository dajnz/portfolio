<?php

declare(strict_types=1);

namespace App\Features\Auth\App\UseCases;

use App\Features\Auth\App\DTOs\CreatedAdminDto;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Exceptions\AdminAlreadyExistsException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use InvalidArgumentException;

/**
 * Creates the initial admin account or, when the single admin already
 * exists and `force` is set, overwrites its login/email while preserving
 * the password and security state.
 */
final class CreateAdminUseCase
{
    public function __construct(
        private readonly AdminRepositoryInterface $adminRepository,
    ) {
    }

    /**
     * Whether the single admin account already exists (the CLI uses this
     * for its prompt/flag flow; `create()` still enforces the invariant).
     */
    public function exists(): bool
    {
        return $this->adminRepository->findFirst() !== null;
    }

    /**
     * @throws AdminAlreadyExistsException when an account exists and force is not set
     * @throws InvalidArgumentException when an account is being created without a password
     */
    public function create(
        string $login,
        string $email,
        ?string $password,
        bool $force,
    ): CreatedAdminDto {
        $existing = $this->adminRepository->findFirst();

        if ($existing !== null) {
            return $this->overwrite($existing, $login, $email, $force);
        }

        if ($password === null || $password === '') {
            throw new InvalidArgumentException('A password is required to create a new admin account.');
        }

        $this->adminRepository->create($login, $email, $login, $password);

        return new CreatedAdminDto(created: true, login: $login);
    }

    private function overwrite(
        Admin $existing,
        string $login,
        string $email,
        bool $force,
    ): CreatedAdminDto {
        if (! $force) {
            throw new AdminAlreadyExistsException();
        }

        $this->adminRepository->save(
            $existing->updateCredentials($existing->toName(), $email, $login),
        );

        return new CreatedAdminDto(created: false, login: $login);
    }
}
