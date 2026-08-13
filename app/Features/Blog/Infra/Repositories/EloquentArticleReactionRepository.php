<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Blog\Domain\Entities\ArticleReaction;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Blog\Infra\Mappers\ArticleReactionMapper;
use App\Features\Blog\Infra\Models\ArticleReactionModel;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed repository for the article reactions: the unique
 * `(article_id, fingerprint)` rows with the `like`/`dislike` CHECK.
 * The upsert semantics (a different type updates the row, the same
 * type deletes it — undo) are the consuming use case's decision; this
 * repository persists single rows and reports the totals.
 */
final class EloquentArticleReactionRepository implements ArticleReactionRepositoryInterface
{
    public function __construct(
        private readonly ArticleReactionMapper $mapper,
    ) {
    }

    public function findByArticleAndFingerprint(int $articleId, string $fingerprint): ?ArticleReaction
    {
        $model = ArticleReactionModel::query()
            ->where('article_id', $articleId)
            ->where('fingerprint', $fingerprint)
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function save(ArticleReaction $reaction): void
    {
        ArticleReactionModel::query()->updateOrCreate(
            [
                'article_id' => $reaction->toArticleId(),
                'fingerprint' => $reaction->toFingerprint(),
            ],
            [
                'reaction' => $reaction->toReaction()->value,
            ],
        );
    }

    public function delete(int $articleId, string $fingerprint): void
    {
        ArticleReactionModel::query()
            ->where('article_id', $articleId)
            ->where('fingerprint', $fingerprint)
            ->delete();
    }

    public function countsFor(int $articleId): array
    {
        $counts = [
            'likes' => 0,
            'dislikes' => 0,
        ];

        foreach (DB::table('article_reactions')
            ->where('article_id', $articleId)
            ->selectRaw('reaction, count(*) as total')
            ->groupBy('reaction')
            ->get() as $row) {
            $counts[$row->reaction === 'dislike' ? 'dislikes' : 'likes'] = (int) $row->total;
        }

        return $counts;
    }
}
