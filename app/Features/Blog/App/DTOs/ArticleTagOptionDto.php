<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\Domain\Entities\TechSkillTag;

/**
 * A tag/skill option of an article's tag pills — the unified wire
 * shape of the `article_tag_pivot` + `article_skill_pivot` rows with
 * the `type` discriminator (`tag` | `skill`). Immutable.
 */
final readonly class ArticleTagOptionDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
    ) {
    }

    public static function forArticleTag(TagOptionDto $tag): self
    {
        return new self(id: $tag->id, name: $tag->name, type: 'tag');
    }

    public static function forSkillTag(TechSkillTag $tag): self
    {
        return new self(
            id: $tag->toId(),
            name: $tag->toSkillName(),
            type: 'skill',
        );
    }
}
