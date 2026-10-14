<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Symmetric encryption of stored secrets (the mail API token) at rest. An
 * abstraction over the framework's encrypter so the app layer never names it;
 * the framework-backed implementation lives on the infra layer.
 */
interface EncrypterInterface
{
    public function encrypt(string $value): string;

    public function decrypt(string $payload): string;
}
