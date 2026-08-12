<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\RepositoryInterfaces\PublishedArticleReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * The {@see PublishedArticleReadRepositoryInterface} over
 * `blog_articles` with the `en`-fallback title (content-management.md:
 * 133 — only currently published, non-trashed articles qualify).
 */
final class EloquentPublishedArticleReadRepository implements PublishedArticleReadRepositoryInterface
{
    public function findPublishedById(int $articleId): ?array
    {
        $row = DB::table('blog_articles as ba')
            ->leftJoin('blog_article_translations as en', function ($join): void {
                $join->on('en.article_id', '=', 'ba.id')
                    ->where('en.locale', '=', 'en');
            })
            ->where('ba.id', $articleId)
            ->where('ba.is_published', true)
            ->whereNull('ba.deleted_at')
            ->select(['ba.slug', DB::raw('coalesce(en.title, \'\') as title')])
            ->first();

        return $row === null
            ? null
            : [
                'id' => $articleId,
                'slug' => $row->slug,
                'title' => $row->title,
            ];
    }
}
