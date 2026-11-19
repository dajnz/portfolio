<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Jobs;

use App\Features\Projects\App\UseCases\GenerateScreenshotThumbnailUseCase;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The queued card-thumbnail generation of one project screenshot. The
 * skip policy, the guarded row update and the cache flush live in
 * {@see GenerateScreenshotThumbnailUseCase}, not here.
 */
final class GenerateScreenshotThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $screenshotId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'screenshot-thumbnail:'.$this->screenshotId;
    }

    public function handle(GenerateScreenshotThumbnailUseCase $thumbnails): void
    {
        $thumbnails->generate($this->screenshotId);
    }
}
