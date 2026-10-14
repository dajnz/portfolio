<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AuthGuardInterface;
use Illuminate\Support\Facades\Auth;

/**
 * The framework's auth guard behind the app-layer {@see AuthGuardInterface}
 * contract.
 */
final class LaravelAuthGuard implements AuthGuardInterface
{
    public function check(): bool
    {
        return Auth::check();
    }

    public function id(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    public function loginUsingId(int $adminId, bool $remember = false): void
    {
        Auth::loginUsingId($adminId, $remember);
    }
}
