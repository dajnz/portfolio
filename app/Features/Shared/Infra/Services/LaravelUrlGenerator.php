<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\UrlGeneratorInterface;
use Illuminate\Support\Facades\URL;

/**
 * The framework's URL generator behind the app-layer {@see UrlGeneratorInterface}
 * contract.
 */
final class LaravelUrlGenerator implements UrlGeneratorInterface
{
    public function to(string $path): string
    {
        return URL::to($path);
    }
}
