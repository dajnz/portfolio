<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\RepositoryInterfaces;

use App\Features\Auth\Domain\Entities\Admin;

interface AdminRepositoryInterface
{
    public function findById(int $id): ?Admin;

    /** The sole admin record (single-admin invariant), or null. */
    public function findFirst(): ?Admin;

    public function findByLoginOrEmail(string $loginOrEmail): ?Admin;

    public function findByGithubId(string $githubId): ?Admin;

    /** Whether the GitHub ID is already bound to a different admin row. */
    public function githubIdTakenByOther(string $githubId, int $excludeAdminId): bool;

    /**
     * Whether any admin account is linked with GitHub (`github_id` set).
     * The login page's GitHub button requires it (ADR-0013) — without a
     * linked account the OAuth login flow can only fail.
     */
    public function hasGithubLinkedAdmin(): bool;

    /**
     * Creates the admin with a plaintext password (bcrypt-hashed at the
     * persistence boundary via the model's `hashed` cast).
     */
    public function create(string $name, string $email, string $login, string $plainPassword): Admin;

    /** Syncs the mutable identity fields (credentials, GitHub link, session version). */
    public function save(Admin $admin): void;

    /** Re-hashes the password (plaintext input — the model's `hashed` cast applies). */
    public function changePassword(int $adminId, string $plainPassword): void;

    /** Atomic increment; returns the new value. */
    public function incrementSessionVersion(int $adminId): int;
}
