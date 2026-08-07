<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Services;

use Illuminate\Support\Facades\DB;

/**
 * Dashboard stat cards (admin-section-ui.md:224-228, B32). Query-builder
 * reads inside the Portfolio Infra layer (B11 — the consuming feature
 * must not import Blog/Projects classes):
 *
 * - count cards (projects/articles) exclude trashed rows;
 * - "Total Views" = `SUM(article_views.views)` across ALL articles,
 *   including unpublished and trashed;
 * - "Blog Views" = the same sum restricted to `is_published = true`,
 *   non-soft-deleted articles.
 */
final class DashboardStatsReader
{
    /**
     * @return array{
     *     total_views: int,
     *     blog_views: int,
     *     projects_count: int,
     *     articles_count: int,
     * }
     */
    public function read(): array
    {
        return [
            'total_views' => $this->totalViews(),
            'blog_views' => $this->blogViews(),
            'projects_count' => DB::table('projects')->whereNull('deleted_at')->count(),
            'articles_count' => DB::table('blog_articles')->whereNull('deleted_at')->count(),
        ];
    }

    private function totalViews(): int
    {
        return (int) DB::table('article_views')->sum('views');
    }

    private function blogViews(): int
    {
        return (int) DB::table('article_views')
            ->join('blog_articles', 'blog_articles.id', '=', 'article_views.article_id')
            ->where('blog_articles.is_published', true)
            ->whereNull('blog_articles.deleted_at')
            ->sum('article_views.views');
    }
}
