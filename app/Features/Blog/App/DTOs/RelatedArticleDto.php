<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * A related-article row of the admin surfaces — `{id, title}` (the
 * Related column chips and the edit form's light rows). Immutable.
 */
final readonly class RelatedArticleDto
{
    public function __construct(
        public int $id,
        public string $title,
    ) {
    }
}
