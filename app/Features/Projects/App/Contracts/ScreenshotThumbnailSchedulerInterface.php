<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Contracts;

/**
 * Runs the card thumbnail generation for the given screenshots — queued
 * by default, inline in the `scheduleNow` backfill mode. The scheduling
 * mechanism lives on the infra layer.
 */
interface ScreenshotThumbnailSchedulerInterface
{
    /**
     * @param  int[]  $screenshotIds
     */
    public function schedule(array $screenshotIds): void;

    /**
     * @param  int[]  $screenshotIds
     */
    public function scheduleNow(array $screenshotIds): void;
}
