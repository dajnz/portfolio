<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

final readonly class CleanupOrphanedFilesResultDto
{
    /**
     * @param  string[]  $orphanPaths
     */
    public function __construct(
        public bool $dryRun,
        public array $orphanPaths,
    ) {
    }
}
