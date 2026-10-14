<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Verifies a plaintext value against a stored password hash — the
 * current-password and login credential checks. An abstraction over the
 * framework's hasher so the app layer never names it; the framework-backed
 * implementation lives on the infra layer.
 */
interface PasswordHasherInterface
{
    public function verify(string $plain, string $hash): bool;
}
