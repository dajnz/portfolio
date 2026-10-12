<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Services;

use App\Features\Blog\App\DTOs\ArticleDraftDto;
use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Entities\ArticleTranslation;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\ValueObjects\Slug;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

/**
 * Builds {@see Article} aggregates from the app-layer draft DTO —
 * shared by the store/update use cases. All value normalization
 * (per-locale maps, the lead image) lives here; the use cases only
 * resolve the slug, decide the publication state and delegate
 * persistence.
 */
final class ArticleEntityFactory
{
    public function make(
        ArticleId $id,
        Slug $slug,
        PublishedState $published,
        ArticleDraftDto $draft,
    ): Article {
        return new Article(
            id: $id,
            slug: $slug,
            leadImage: $this->leadImage($draft),
            published: $published,
            translations: $this->translations($draft),
            articleTagIds: $this->distinctPositiveIds($draft->tagIds),
            skillTagIds: $this->distinctPositiveIds($draft->skillTagIds),
            relatedArticleIds: $this->distinctPositiveIds($draft->relatedArticleIds),
        );
    }

    /**
     * @return ArticleTranslation[]
     */
    private function translations(ArticleDraftDto $draft): array
    {
        $entities = [];

        foreach ($draft->translations->titles as $locale => $title) {
            if (! is_string($title) || $title === '') {
                continue;
            }

            $entities[] = new ArticleTranslation(
                locale: (string) $locale,
                title: $title,
                shortDescription: $this->requiredString($draft->translations->shortDescriptions[$locale] ?? null),
                content: $this->requiredString($draft->translations->contents[$locale] ?? null),
            );
        }

        return $entities;
    }

    private function leadImage(ArticleDraftDto $draft): ?StoredPicture
    {
        $lead = $draft->leadImage;

        if ($lead === null || $lead->url === '') {
            return null;
        }

        return new StoredPicture(
            url: $lead->url,
            altLabels: $this->nonEmptyLocaleMap($lead->altLabels),
        );
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function nonEmptyLocaleMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $locale => $text) {
            if (is_string($text) && $text !== '') {
                $normalized[(string) $locale] = $text;
            }
        }

        return $normalized;
    }

    private function requiredString(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : '';
    }

    /**
     * @param  int[]  $ids
     * @return int[]
     */
    private function distinctPositiveIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
