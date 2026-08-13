<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Mappers;

use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;

/**
 * Converts between `article_tag_translations` rows and
 * {@see ArticleTagTranslation} value objects.
 */
final class ArticleTagTranslationMapper
{
    public function toEntity(ArticleTagTranslationModel $model): ArticleTagTranslation
    {
        return new ArticleTagTranslation(
            locale: $model->locale,
            name: $model->name,
        );
    }

    /**
     * @return array{article_tag_id: int, locale: string, name: string}
     */
    public function toRow(int $tagId, ArticleTagTranslation $translation): array
    {
        return [
            'article_tag_id' => $tagId,
            'locale' => $translation->toLocale(),
            'name' => $translation->toName(),
        ];
    }
}
