<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\UnifiedTagOptionDto;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * The unified blog-tag autocomplete (`GET /api/blog-tags/search`,
 * routes.md:197, content-management.md:198-200): case-insensitive
 * name contains-match over BOTH article tags (`type: "tag"`) and
 * tech skills (`type: "skill"`) — the `type` discriminator keeps the
 * independent id spaces unambiguous when the filter URL is built
 * (`?article_tags=` vs `?skill_tags=`). Each source is ordered by
 * name ascending; the merged result is capped at 20 (ADR-0007).
 */
final class SearchUnifiedBlogTagsUseCase
{
    private const MAX_RESULTS = 20;

    public function __construct(
        private readonly PublicArticleReadRepositoryInterface $articleTags,
        private readonly TechSkillReadRepositoryInterface $skills,
    ) {
    }

    /**
     * @return UnifiedTagOptionDto[]
     */
    public function search(string $query, string $locale): array
    {
        if ($query === '') {
            return [];
        }

        $needle = mb_strtolower($query);

        $tags = array_values(array_filter(
            $this->articleTags->findAllTags($locale),
            static fn (object $tag): bool => str_contains(mb_strtolower($tag->name), $needle),
        ));

        $skills = array_values(array_filter(
            $this->skills->findAll($locale),
            static fn (TechSkill $skill): bool => str_contains(mb_strtolower($skill->toName()), $needle),
        ));

        $options = [
            ...array_map(
                static fn (object $tag): UnifiedTagOptionDto => new UnifiedTagOptionDto(
                    id: (int) $tag->id,
                    name: $tag->name,
                    type: 'tag',
                ),
                $tags,
            ),
            ...array_map(
                static fn (TechSkill $skill): UnifiedTagOptionDto => new UnifiedTagOptionDto(
                    id: $skill->toId(),
                    name: $skill->toName(),
                    type: 'skill',
                ),
                $skills,
            ),
        ];

        usort(
            $options,
            static fn (UnifiedTagOptionDto $a, UnifiedTagOptionDto $b): int => strcmp(
                mb_strtolower($a->name).'|'.$a->type,
                mb_strtolower($b->name).'|'.$b->type,
            ),
        );

        return array_slice($options, 0, self::MAX_RESULTS);
    }
}
