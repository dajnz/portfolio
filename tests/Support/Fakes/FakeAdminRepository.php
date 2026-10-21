<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;

final class FakeAdminRepository implements AdminRepositoryInterface
{
    public ?Admin $admin = null;

    /** @var list<array{name: string, email: string, login: string, password: string}> */
    public array $created = [];

    /** @var list<Admin> */
    public array $saved = [];

    /** @var list<array{adminId: int, password: string}> */
    public array $passwordChanges = [];

    public int $sessionVersion = 0;

    public function findById(int $id): ?Admin
    {
        return $this->admin?->toId() === $id ? $this->admin : null;
    }

    public function findFirst(): ?Admin
    {
        return $this->admin;
    }

    public function findByLoginOrEmail(string $loginOrEmail): ?Admin
    {
        if ($this->admin === null) {
            return null;
        }

        return $this->admin->toLogin() === $loginOrEmail || $this->admin->toEmail() === $loginOrEmail
            ? $this->admin
            : null;
    }

    public function findByGithubId(string $githubId): ?Admin
    {
        return null;
    }

    public function githubIdTakenByOther(string $githubId, int $excludeAdminId): bool
    {
        return false;
    }

    public function hasGithubLinkedAdmin(): bool
    {
        return false;
    }

    public function create(string $name, string $email, string $login, string $plainPassword): Admin
    {
        $this->created[] = compact('name', 'email', 'login', 'plainPassword');

        $this->admin = new Admin(
            id: 1,
            name: $name,
            email: $email,
            login: $login,
            passwordHash: 'stored-hash',
            githubId: null,
            githubToken: null,
            sessionVersion: 0,
            timedOtp: AdminTimedOtp::disabled(),
        );

        return $this->admin;
    }

    public function save(Admin $admin): void
    {
        $this->admin = $admin;
        $this->saved[] = $admin;
    }

    public function changePassword(int $adminId, string $plainPassword): void
    {
        $this->passwordChanges[] = ['adminId' => $adminId, 'password' => $plainPassword];
    }

    public function incrementSessionVersion(int $adminId): int
    {
        $this->sessionVersion++;

        return $this->sessionVersion;
    }
}
