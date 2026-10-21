<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleViewRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * The atomic `article_views` counter increment. A single upsert statement
 * is required: `updateOrCreate + increment` is not atomic when two
 * concurrent requests both see no existing row.
 */
final class EloquentArticleViewRepository implements ArticleViewRepositoryInterface
{
    public function increment(int $articleId): void
    {
        DB::statement(
            'INSERT INTO article_views (article_id, views) VALUES (?, 1)'
            .' ON CONFLICT (article_id) DO UPDATE SET views = article_views.views + 1',
            [$articleId],
        );
    }
}
