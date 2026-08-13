<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;

/**
 * In-memory {@see ArticleTagRepositoryInterface} for the use-case unit
 * tests: the tag↔skill name probe is configurable (the cross-check
 * direction — content-management.md:695), the write methods record
 * their calls.
 */
final class FakeArticleTagRepository implements ArticleTagRepositoryInterface
{
    /**
     * @param  string[]  $skillNames  The existing skill names (any locale).
     * @param  array<int, ArticleTag>  $tags  Tag id => stored tag.
     * @param  string[]  $taken  Existing tag slugs (the A3 suffix probe).
     */
    public function __construct(
        public array $skillNames = [],
        public array $tags = [],
        public array $taken = [],
    ) {
    }

    public array $savedTags = [];

    public array $deletedIds = [];

    public function findById(int $id): ?ArticleTag
    {
        return $this->tags[$id] ?? null;
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        return $this->taken;
    }

    public function anyTagNameMatchesSkill(array $names, ?int $ignoreId = null): bool
    {
        $lower = array_map('mb_strtolower', $names);
        $skillNames = array_map('mb_strtolower', $this->skillNames);

        return array_intersect($lower, $skillNames) !== [];
    }

    public function save(ArticleTag $tag): int
    {
        $this->savedTags[] = $tag;

        return $tag->toId() > 0 ? $tag->toId() : count($this->savedTags);
    }

    public function delete(int $id): void
    {
        $this->deletedIds[] = $id;
    }
}
