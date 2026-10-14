<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The request's session store — the half-auth pending state, the session
 * version and the TOTP enrollment secret are read and written through it. An
 * abstraction over the framework's session so the app layer never names it;
 * the framework-backed implementation lives on the infra layer.
 */
interface SessionStoreInterface
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
}
