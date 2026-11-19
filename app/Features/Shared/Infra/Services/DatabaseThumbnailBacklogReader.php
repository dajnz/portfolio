<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\ThumbnailBacklogReaderInterface;
use Illuminate\Support\Facades\DB;

final class DatabaseThumbnailBacklogReader implements ThumbnailBacklogReaderInterface
{
    public function screenshotIds(bool $includeGenerated = false): array
    {
        return $this->ids(
            DB::table('project_screenshots')
                ->when(! $includeGenerated, static fn ($query) => $query->whereNull('thumbnail_path')),
        );
    }

    public function articleIds(bool $includeGenerated = false): array
    {
        return $this->ids(
            DB::table('blog_articles')
                ->whereNotNull('lead_image')
                ->where('lead_image', '!=', '')
                ->when(! $includeGenerated, static fn ($query) => $query->whereNull('thumbnail_path')),
        );
    }

    /**
     * @return int[]
     */
    private function ids(\Illuminate\Database\Query\Builder $query): array
    {
        return $query
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
