<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Mappers;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Entities\ArticleTranslation;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\ValueObjects\Slug;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;

/**
 * Converts between `blog_articles` rows (with their translations
 * pre-loaded via the relation and the pivot ids passed separately)
 * and {@see Article} entities, and back to the base-row data. The
 * model's Carbon dates are converted to `DateTimeImmutable` at the
 * boundary — the domain stays Carbon-free.
 */
final class ArticleMapper
{
    public function __construct(
        private readonly ArticleTranslationMapper $translationMapper,
    ) {
    }

    /**
     * @param  int[]  $articleTagIds  The `article_tag_pivot` ids.
     * @param  int[]  $skillTagIds  The `article_skill_pivot` ids.
     * @param  int[]  $relatedArticleIds  The `related_articles` ids.
     */
    public function toEntity(
        ArticleModel $model,
        array $articleTagIds = [],
        array $skillTagIds = [],
        array $relatedArticleIds = [],
    ): Article {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new Article(
            id: new ArticleId((int) $model->id),
            slug: new Slug($model->slug),
            leadImage: $this->leadImage($model),
            published: new PublishedState(
                isPublished: (bool) $model->is_published,
                publishedAt: $model->published_at === null
                    ? null
                    : $this->toImmutable($model->published_at),
            ),
            translations: $translations
                ->map(fn (mixed $row): ArticleTranslation => $this->translationMapper->toEntity($row))
                ->all(),
            articleTagIds: $articleTagIds,
            skillTagIds: $skillTagIds,
            relatedArticleIds: $relatedArticleIds,
            updatedAt: $model->updated_at === null
                ? null
                : $this->toImmutable($model->updated_at),
        );
    }

    /**
     * @return array{
     *     slug: string,
     *     lead_image: string|null,
     *     lead_image_alt: array<string, string>|null,
     *     is_published: bool,
     *     published_at: string|null,
     * }
     */
    public function toRow(Article $article): array
    {
        $leadImage = $article->toLeadImage();

        return [
            'slug' => $article->toSlug(),
            'lead_image' => $leadImage?->url,
            'lead_image_alt' => $leadImage === null || $leadImage->altLabelsMap() === []
                ? null
                : $leadImage->altLabelsMap(),
            'is_published' => $article->toIsPublished(),
            'published_at' => $article->toPublishedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function leadImage(ArticleModel $model): ?StoredPicture
    {
        if ($model->lead_image === null) {
            return null;
        }

        $altLabels = [];
        foreach ((array) ($model->lead_image_alt ?? []) as $locale => $alt) {
            if (is_string($alt) && $alt !== '') {
                $altLabels[(string) $locale] = $alt;
            }
        }

        return new StoredPicture(
            url: $model->lead_image,
            altLabels: $altLabels,
        );
    }

    private function toImmutable(mixed $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        return new DateTimeImmutable($date->toDateTimeString());
    }
}
