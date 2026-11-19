<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Services;

use App\Features\Blog\App\Contracts\ArticleThumbnailStoreInterface;
use Illuminate\Support\Facades\DB;

final class EloquentArticleThumbnailStore implements ArticleThumbnailStoreInterface
{
    public function leadImagePathOf(int $articleId): ?string
    {
        $path = DB::table('blog_articles')
            ->where('id', $articleId)
            ->value('lead_image');

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function store(int $articleId, string $sourcePath, string $thumbnailPath): void
    {
        DB::table('blog_articles')
            ->where('id', $articleId)
            ->where('lead_image', $sourcePath)
            ->update(['thumbnail_path' => $thumbnailPath]);
    }
}
