<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Services;

use App\Features\Blog\App\Contracts\ArticleThumbnailSchedulerInterface;
use App\Features\Blog\Infra\Jobs\GenerateArticleThumbnailJob;
use Illuminate\Support\Facades\Bus;

final class QueuedArticleThumbnailScheduler implements ArticleThumbnailSchedulerInterface
{
    public function schedule(array $articleIds): void
    {
        foreach (array_unique($articleIds) as $articleId) {
            Bus::dispatch(new GenerateArticleThumbnailJob($articleId));
        }
    }

    public function scheduleNow(array $articleIds): void
    {
        foreach (array_unique($articleIds) as $articleId) {
            Bus::dispatchSync(new GenerateArticleThumbnailJob($articleId));
        }
    }
}
