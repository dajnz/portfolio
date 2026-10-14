<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The configured default cap (px) for the largest side of an uploaded
 * image, used as the fallback when the admin has not overridden the
 * setting. An abstraction over the committed
 * `config('portfolio.image_max_dimension')` value so the app layer never
 * reads framework configuration; the framework-backed implementation
 * lives on the infra layer.
 */
interface UploadDefaultsInterface
{
    public function imageMaxDimension(): int;
}
