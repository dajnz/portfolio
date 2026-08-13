<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Mappers;

use App\Features\Blog\Domain\Entities\ArticleTranslation;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;

/**
 * Converts between `blog_article_translations` rows and
 * {@see ArticleTranslation} value objects. The stored
 * `reading_time_minutes` is carried for the round-trip (recomputed on
 * every save by the model events / the repository's batch rebuild).
 */
final class ArticleTranslationMapper
{
    public function toEntity(ArticleTranslationModel $model): ArticleTranslation
    {
        return new ArticleTranslation(
            locale: $model->locale,
            title: $model->title,
            shortDescription: $model->short_description,
            content: $model->content,
            readingTimeMinutes: (int) $model->reading_time_minutes,
        );
    }

    /**
     * @return array{article_id: int, locale: string, title: string, short_description: string, content: string, reading_time_minutes: int}
     */
    public function toRow(int $articleId, ArticleTranslation $translation): array
    {
        return [
            'article_id' => $articleId,
            'locale' => $translation->toLocale(),
            'title' => $translation->toTitle(),
            'short_description' => $translation->toShortDescription(),
            'content' => $translation->toContent(),
            'reading_time_minutes' => $translation->toReadingTimeMinutes(),
        ];
    }
}
