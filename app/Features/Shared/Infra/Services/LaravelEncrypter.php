<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\EncrypterInterface;
use Illuminate\Support\Facades\Crypt;

/**
 * The framework encrypter behind the app-layer {@see EncrypterInterface}
 * contract.
 */
final class LaravelEncrypter implements EncrypterInterface
{
    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function decrypt(string $payload): string
    {
        return Crypt::decryptString($payload);
    }
}
