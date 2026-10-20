<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

final readonly class UploadAssetDto
{
    public function __construct(
        public string $sourcePath,
        public string $originalName,
        public string $mimeType,
        public string $entityType,
    ) {
    }
}
