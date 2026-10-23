<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The admin's request session: the framework session store (the half-auth
 * pending state, the session version, the TOTP enrollment secret) and the
 * authentication guard (whether a session is authenticated, which admin it
 * belongs to, logging an admin in by id). An abstraction over the framework's
 * session + auth stack so the app layer never names them; the framework-backed
 * implementation lives on the infra layer.
 */
interface AdminSessionInterface
{
    public function get(string $key): mixed;

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void;

    /**
     * @param  string[]  $keys
     */
    public function forget(array $keys): void;

    /** Reads a value and removes it in one step. */
    public function pull(string $key): mixed;

    /** Rotates the session ID, preserving the session data. */
    public function regenerate(): void;

    public function check(): bool;

    public function id(): ?int;

    public function loginUsingId(int $adminId, bool $remember = false): void;
}
