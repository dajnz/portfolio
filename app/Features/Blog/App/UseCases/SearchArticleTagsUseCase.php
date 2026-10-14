<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;

/**
 * The article-tags autocomplete (`GET /api/tags/search`):
 * case-insensitive name contains-match over ALL article tags — matched
 * against every locale name (a tag is found by any of its localized
 * names) while the returned name is resolved to the request locale (the
 * `en` fallback). Ordered by name ascending, capped at 20 results.
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

        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            $this->repository->searchTags($query, $locale, self::MAX_RESULTS),
        );
    }
}
