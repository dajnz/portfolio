<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Contracts;

/**
 * Runs the card thumbnail generation for the given articles — queued by
 * default, inline in the `scheduleNow` backfill mode. The scheduling
 * mechanism lives on the infra layer.
 */
interface ArticleThumbnailSchedulerInterface
{
    /**
     * @param  int[]  $articleIds
     */
    public function schedule(array $articleIds): void;

    /**
     * @param  int[]  $articleIds
     */
    public function scheduleNow(array $articleIds): void;
}
