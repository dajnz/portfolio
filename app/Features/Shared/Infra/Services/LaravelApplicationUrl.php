<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\ApplicationUrlInterface;
use Illuminate\Support\Facades\Config;

/**
 * The framework's `app.url` behind the app-layer {@see ApplicationUrlInterface}
 * contract, normalized to a trailing-slash-free origin.
 */
final class LaravelApplicationUrl implements ApplicationUrlInterface
{
    public function baseUrl(): string
    {
        return rtrim((string) Config::get('app.url'), '/');
    }
}
