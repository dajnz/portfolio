<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

final readonly class UploadedAssetDto
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $size,
    ) {
    }
}
