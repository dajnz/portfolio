<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\TagDraftDto;
use App\Features\Blog\App\Services\TagSlugService;
use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use App\Features\Blog\Domain\Exceptions\DuplicateArticleTagNameException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Creates an article tag (content-management.md:690-695): the slug is
 * resolved per A3 (auto-generated from the EN name with a collision
 * suffix, or the manual override kept as-is) and the tag↔skill name
 * cross-check runs for every submitted locale name — a name matching
 * an existing skill (case-insensitive, any locale) throws
 * {@see DuplicateArticleTagNameException}, surfaced as a `name`
 * validation error by the controller (B130/B132). Shared by the admin
 * form (`POST /admin/tags`) and the inline API creation
 * (`POST /api/tags`).
 */
final class StoreTagUseCase
{
    public function __construct(
        private readonly ArticleTagRepositoryInterface $repository,
        private readonly TagSlugService $slugs,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function store(TagDraftDto $draft): ArticleTag
    {
        $this->assertNamesDoNotMatchSkills($draft->names, null);

        $tag = new ArticleTag(
            id: 0,
            slug: $this->slugs->resolve($draft->submittedSlug, null, $draft->enName(), null),
            translations: $this->translations($draft->names),
        );

        $id = $this->repository->save($tag);

        $this->countCache->invalidate('tags');

        return $tag->withId($id);
    }

    /**
     * @param  array<string, string|null>  $names
     * @return ArticleTagTranslation[]
     */
    private function translations(array $names): array
    {
        $translations = [];

        foreach ($names as $locale => $name) {
            if (is_string($name) && $name !== '') {
                $translations[] = new ArticleTagTranslation(locale: (string) $locale, name: $name);
            }
        }

        return $translations;
    }

    /**
     * @param  array<string, string|null>  $names
     */
    private function assertNamesDoNotMatchSkills(array $names, ?int $ignoreId): void
    {
        if ($this->repository->anyTagNameMatchesSkill(array_values($names), $ignoreId)) {
            throw new DuplicateArticleTagNameException();
        }
    }
}
