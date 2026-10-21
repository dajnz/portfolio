<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Jobs;

use App\Features\Blog\App\UseCases\IncrementArticleViewUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The queued per-view counter increment: dispatched by the public article
 * controller through `RecordArticleViewUseCase`. The bot policy and the
 * atomic increment live in `IncrementArticleViewUseCase`, not here.
 */
final class IncrementArticleViewJob implements ShouldQueue
{
    public function __construct(
        public readonly int $articleId,
        public readonly ?string $userAgent,
    ) {
    }

    public function handle(IncrementArticleViewUseCase $increment): void
    {
        $increment->increment($this->articleId, $this->userAgent);
    }
}
