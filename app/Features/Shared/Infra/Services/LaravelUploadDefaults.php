<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\UploadDefaultsInterface;
use Illuminate\Support\Facades\Config;

/**
 * The committed `config('portfolio.image_max_dimension')` default behind
 * the app-layer {@see UploadDefaultsInterface} contract.
 */
final class LaravelUploadDefaults implements UploadDefaultsInterface
{
    public function imageMaxDimension(): int
    {
        return (int) Config::get('portfolio.image_max_dimension', 2048);
    }
}
