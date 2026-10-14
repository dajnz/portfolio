<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The admin authentication guard: whether a session is authenticated, which
 * admin it belongs to, and logging an admin in by id (the credential check
 * happens in the use case, never before TOTP verification succeeds). An
 * abstraction over the framework's auth guard so the app layer never names it;
 * the framework-backed implementation lives on the infra layer.
 */
interface AuthGuardInterface
{
    public function check(): bool;

    public function id(): ?int;

    public function loginUsingId(int $adminId, bool $remember = false): void;
}
