<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;

/**
 * The article-tags autocomplete (`GET /api/tags/search`, routes.md:
 * 196): case-insensitive name contains-match over ALL article tags
 * (the article-tags-only source of the admin tag form and the blog
 * filter), ordered by name ascending, capped at 20 results (A11).
 */
final class SearchArticleTagsUseCase
{
    private const MAX_RESULTS = 20;

    public function __construct(
        private readonly PublicArticleReadRepositoryInterface $repository,
    ) {
    }

    /**
     * @return TagOptionDto[]
     */
    public function search(string $query, string $locale): array
    {
        if ($query === '') {
            return [];
        }

        $needle = mb_strtolower($query);

        $matches = array_values(array_filter(
            $this->repository->findAllTags($locale),
            static fn (object $tag): bool => str_contains(mb_strtolower($tag->name), $needle),
        ));

        usort(
            $matches,
            static fn (object $a, object $b): int => strcmp(mb_strtolower($a->name), mb_strtolower($b->name)),
        );

        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            array_slice($matches, 0, self::MAX_RESULTS),
        );
    }
}
