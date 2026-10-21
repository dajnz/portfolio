<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

final readonly class PruneTrashedItemsResultDto
{
    public function __construct(
        public int $retentionDays,
        public int $deleted,
    ) {
    }
}
