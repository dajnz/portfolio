<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\SessionStoreInterface;
use Illuminate\Support\Facades\Session;

/**
 * The framework's session store behind the app-layer {@see SessionStoreInterface}
 * contract.
 */
final class LaravelSessionStore implements SessionStoreInterface
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
}
