<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Mappers;

use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Shared\Domain\ValueObjects\Slug;

/**
 * Converts between `article_tags` rows (with their translations
 * pre-loaded via the relation) and {@see ArticleTag} entities, and
 * back to the base-row data.
 */
final class ArticleTagMapper
{
    public function __construct(
        private readonly ArticleTagTranslationMapper $translationMapper,
    ) {
    }

    public function toEntity(ArticleTagModel $model): ArticleTag
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new ArticleTag(
            id: (int) $model->id,
            slug: new Slug($model->slug),
            translations: $translations
                ->map(fn (mixed $row): ArticleTagTranslation => $this->translationMapper->toEntity($row))
                ->all(),
        );
    }

    /**
     * @return array{slug: string}
     */
    public function toRow(ArticleTag $tag): array
    {
        return [
            'slug' => $tag->toSlug(),
        ];
    }
}
