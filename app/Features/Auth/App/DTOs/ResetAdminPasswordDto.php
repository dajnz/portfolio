<?php

declare(strict_types=1);

namespace App\Features\Auth\App\DTOs;

final readonly class ResetAdminPasswordDto
{
    public function __construct(
        public string $login,
    ) {
    }
}
