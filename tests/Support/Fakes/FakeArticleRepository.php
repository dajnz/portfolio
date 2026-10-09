<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;

/**
 * In-memory {@see ArticleRepositoryInterface} for the use-case unit
 * tests: the slug-related reads are configurable (the restore
 * uniqueness re-check — ADR-0006), the write methods record their calls.
 */
final class FakeArticleRepository implements ArticleRepositoryInterface
{
    /**
     * @param  array<int, string>  $trashedSlugs  Article id => slug (soft-deleted).
     * @param  string[]  $activeSlugs  Slugs occupied by non-trashed articles.
     * @param  int[]  $publishedIds  Article ids that are published (soft-deleted included).
     * @param  array<int, int[]>  $relatedIds  Article id => already-linked article ids.
     */
    public function __construct(
        public array $trashedSlugs = [],
        public array $activeSlugs = [],
        public array $publishedIds = [],
        public array $relatedIds = [],
    ) {
    }

    public array $restoredIds = [];

    public array $restoredManyIds = [];

    public function findById(int $id): ?Article
    {
        return null;
    }

    public function findByIdIncludingTrashed(int $id): ?Article
    {
        return null;
    }

    public function findBySlug(string $slug): ?Article
    {
        return null;
    }

    public function findBySlugIncludingUnpublished(string $slug): ?Article
    {
        return null;
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        return [];
    }

    public function slugsOfArticles(array $ids): array
    {
        return array_intersect_key($this->trashedSlugs, array_flip($ids));
    }

    public function slugsOccupiedByActive(array $slugs): array
    {
        return array_values(array_intersect($slugs, $this->activeSlugs));
    }

    public function publishedIdsOf(array $ids): array
    {
        return array_values(array_intersect($ids, $this->publishedIds));
    }

    public function relatedIdsOf(int $articleId): array
    {
        return $this->relatedIds[$articleId] ?? [];
    }

    public function currentSlugForOld(string $oldSlug): ?string
    {
        return null;
    }

    public function assetPathsOf(array $ids): array
    {
        return [];
    }

    public function save(Article $article): void
    {
    }

    public function delete(int $id): void
    {
    }

    public function deleteMany(array $ids): void
    {
    }

    public function restore(int $id): void
    {
        $this->restoredIds[] = $id;
    }

    public function restoreMany(array $ids): void
    {
        $this->restoredManyIds = $ids;
    }

    public function forceDelete(int $id): void
    {
    }

    public function forceDeleteMany(array $ids): void
    {
    }

    public function setPublicationMany(array $ids, bool $published): void
    {
    }

    public function attachRelated(int $articleId, int $relatedArticleId): void
    {
    }

    public function detachRelated(int $articleId, int $relatedArticleId): void
    {
    }

    public function attachSkill(int $articleId, int $skillId): void
    {
    }

    public function detachSkill(int $articleId, int $skillId): void
    {
    }
}
