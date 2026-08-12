<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * A published blog article option of the project form's "Tech Writeup
 * Article" select (admin-section-ui.md:356) — identity, slug and the
 * locale-resolved title. Only published articles can be linked
 * (content-management.md:159). Immutable.
 */
final readonly class ArticleOptionDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {
    }
}
