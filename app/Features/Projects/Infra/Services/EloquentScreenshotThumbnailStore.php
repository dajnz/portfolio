<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Services;

use App\Features\Projects\App\Contracts\ScreenshotThumbnailStoreInterface;
use Illuminate\Support\Facades\DB;

final class EloquentScreenshotThumbnailStore implements ScreenshotThumbnailStoreInterface
{
    public function imagePathOf(int $screenshotId): ?string
    {
        $path = DB::table('project_screenshots')
            ->where('id', $screenshotId)
            ->value('image_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function store(int $screenshotId, string $sourcePath, string $thumbnailPath): void
    {
        DB::table('project_screenshots')
            ->where('id', $screenshotId)
            ->where('image_path', $sourcePath)
            ->update(['thumbnail_path' => $thumbnailPath]);
    }
}
