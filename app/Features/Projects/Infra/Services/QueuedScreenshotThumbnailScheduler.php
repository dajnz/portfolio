<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Services;

use App\Features\Projects\App\Contracts\ScreenshotThumbnailSchedulerInterface;
use App\Features\Projects\Infra\Jobs\GenerateScreenshotThumbnailJob;
use Illuminate\Support\Facades\Bus;

final class QueuedScreenshotThumbnailScheduler implements ScreenshotThumbnailSchedulerInterface
{
    public function schedule(array $screenshotIds): void
    {
        foreach (array_unique($screenshotIds) as $screenshotId) {
            Bus::dispatch(new GenerateScreenshotThumbnailJob($screenshotId));
        }
    }

    public function scheduleNow(array $screenshotIds): void
    {
        foreach (array_unique($screenshotIds) as $screenshotId) {
            Bus::dispatchSync(new GenerateScreenshotThumbnailJob($screenshotId));
        }
    }
}
