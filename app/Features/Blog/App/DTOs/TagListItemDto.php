<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * A tag row of the admin tag list — the `TagTableRowData` wire shape
 * (`{id, slug, name}` plus the `article_tag_pivot` usage count).
 * Immutable.
 */
final readonly class TagListItemDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $articlesUseCount,
    ) {
    }

    /**
     * @param  object  $row  The tag list projection row.
     */
    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            slug: $row->slug,
            name: $row->name,
            articlesUseCount: (int) ($row->articles_use_count ?? 0),
        );
    }
}
