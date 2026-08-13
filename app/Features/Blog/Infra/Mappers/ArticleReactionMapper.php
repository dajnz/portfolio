<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Mappers;

use App\Features\Blog\Domain\Entities\ArticleReaction;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Blog\Domain\ValueObjects\ReactionType;
use App\Features\Blog\Infra\Models\ArticleReactionModel;
use DateTimeImmutable;

/**
 * Converts between `article_reactions` rows and
 * {@see ArticleReaction} entities, and back to the row data.
 */
final class ArticleReactionMapper
{
    public function toEntity(ArticleReactionModel $model): ArticleReaction
    {
        return new ArticleReaction(
            articleId: new ArticleId((int) $model->article_id),
            fingerprint: $model->fingerprint,
            reaction: ReactionType::from($model->reaction),
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            id: (int) $model->id,
        );
    }

    /**
     * @return array{article_id: int, fingerprint: string, reaction: string, created_at: string}
     */
    public function toRow(ArticleReaction $reaction): array
    {
        return [
            'article_id' => $reaction->toArticleId(),
            'fingerprint' => $reaction->toFingerprint(),
            'reaction' => $reaction->toReaction()->value,
            'created_at' => $reaction->toCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
