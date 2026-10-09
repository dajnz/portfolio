<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\TagDraftDto;
use App\Features\Blog\App\Services\TagSlugService;
use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use App\Features\Blog\Domain\Exceptions\ArticleTagNotFoundException;
use App\Features\Blog\Domain\Exceptions\DuplicateArticleTagNameException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Updates an article tag: the slug is re-resolved (a submitted slug
 * equal to the stored one, or to the naive auto-generation of the EN
 * name, is regenerated server-side with a collision suffix; a differing
 * submission is a manual override kept as-is) and the tag↔skill name
 * cross-check runs for every submitted locale name with the tag itself
 * ignored.
 */
final class UpdateTagUseCase
{
    public function __construct(
        private readonly ArticleTagRepositoryInterface $repository,
        private readonly TagSlugService $slugs,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function update(int $id, TagDraftDto $draft): ArticleTag
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw ArticleTagNotFoundException::forId($id);
        }

        $this->assertNamesDoNotMatchSkills($draft->names, $id);

        $tag = new ArticleTag(
            id: $id,
            slug: $this->slugs->resolve($draft->submittedSlug, $existing->toSlug(), $draft->enName(), $id),
            translations: $this->translations($draft->names),
        );

        $this->repository->save($tag);

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
