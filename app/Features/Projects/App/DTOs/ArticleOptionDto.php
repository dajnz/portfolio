<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

final readonly class ArticleOptionDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {
    }
}
