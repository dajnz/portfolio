<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Services;

use App\Features\Projects\App\Contracts\PublishedArticleReaderInterface;
use App\Features\Projects\App\DTOs\ArticleOptionDto;
use Illuminate\Support\Facades\DB;

/**
 * The published-articles read for the project form's "Tech Writeup
 * Article" select: a query-builder read over the Blog feature's tables
 * — cross-feature reads live in the consuming feature's Infra layer.
 * Only published, non-trashed articles qualify; the title resolves the
 * current admin locale with the `en` fallback; newest first.
 */
final class PublishedArticleReader implements PublishedArticleReaderInterface
{
    /**
     * @return ArticleOptionDto[]
     */
    public function findAll(string $locale): array
    {
        return array_map(
            static fn (object $row): ArticleOptionDto => new ArticleOptionDto(
                id: (int) $row->id,
                slug: $row->slug,
                title: $row->title,
            ),
            DB::table('blog_articles as a')
                ->leftJoin('blog_article_translations as tl', function ($join) use ($locale): void {
                    $join->on('tl.article_id', '=', 'a.id')
                        ->where('tl.locale', '=', $locale);
                })
                ->leftJoin('blog_article_translations as te', function ($join): void {
                    $join->on('te.article_id', '=', 'a.id')
                        ->where('te.locale', '=', 'en');
                })
                ->where('a.is_published', true)
                ->whereNull('a.deleted_at')
                ->orderByDesc('a.published_at')
                ->orderByDesc('a.id')
                ->select([
                    'a.id',
                    'a.slug',
                    DB::raw('coalesce(tl.title, te.title, \'\') as title'),
                ])
                ->get()
                ->all(),
        );
    }
}
