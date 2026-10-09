<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\DTOs;

/**
 * The top article of the email digest: the `views` row with the
 * `article:{id}` dimension carrying the most views on the digest day,
 * joined with its EN title from the reader.
 */
final readonly class AnalyticsTopArticleDto
{
    public function __construct(
        public int $id,
        public string $title,
        public float $views,
    ) {
    }
}
