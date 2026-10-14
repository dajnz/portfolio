<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AdminSessionInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The framework's session store and auth guard behind the app-layer
 * {@see AdminSessionInterface} contract.
 */
final class LaravelAdminSession implements AdminSessionInterface
{
    public function get(string $key): mixed
    {
        return Session::get($key);
    }

    public function put(array $values): void
    {
        Session::put($values);
    }

    public function forget(array $keys): void
    {
        Session::forget($keys);
    }

    public function pull(string $key): mixed
    {
        return Session::pull($key);
    }

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
