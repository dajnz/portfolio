<?php

declare(strict_types=1);

namespace App\Features\Auth\App\DTOs;

final readonly class CreatedAdminDto
{
    public function __construct(
        public bool $created,
        public string $login,
    ) {
    }
}
