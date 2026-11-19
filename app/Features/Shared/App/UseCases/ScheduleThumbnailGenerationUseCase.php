<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Blog\App\Contracts\ArticleThumbnailSchedulerInterface;
use App\Features\Projects\App\Contracts\ScreenshotThumbnailSchedulerInterface;
use App\Features\Shared\App\Contracts\ThumbnailBacklogReaderInterface;
use App\Features\Shared\App\DTOs\ThumbnailBacklogDto;

/**
 * The thumbnail backfill: schedules the generation for every stored
 * screenshot and lead image that has no thumbnail yet (or for all of them
 * with `includeGenerated`). The `synchronously` mode runs the generation
 * inline — the demo seeder uses it so the seeded content is complete
 * right after the seed.
 */
final class ScheduleThumbnailGenerationUseCase
{
    public function __construct(
        private readonly ThumbnailBacklogReaderInterface $backlog,
        private readonly ScreenshotThumbnailSchedulerInterface $screenshots,
        private readonly ArticleThumbnailSchedulerInterface $articles,
    ) {
    }

    public function schedule(bool $includeGenerated, bool $synchronously): ThumbnailBacklogDto
    {
        $screenshotIds = $this->backlog->screenshotIds($includeGenerated);
        $articleIds = $this->backlog->articleIds($includeGenerated);

        if ($synchronously) {
            $this->screenshots->scheduleNow($screenshotIds);
            $this->articles->scheduleNow($articleIds);
        } else {
            $this->screenshots->schedule($screenshotIds);
            $this->articles->schedule($articleIds);
        }

        return new ThumbnailBacklogDto(count($screenshotIds), count($articleIds));
    }
}
