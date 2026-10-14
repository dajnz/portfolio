<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\PasswordHasherInterface;
use Illuminate\Support\Facades\Hash;

/**
 * The framework's hasher behind the app-layer {@see PasswordHasherInterface}
 * contract.
 */
final class LaravelPasswordHasher implements PasswordHasherInterface
{
    public function verify(string $plain, string $hash): bool
    {
        return Hash::check($plain, $hash);
    }
}
