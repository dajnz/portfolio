<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\Contracts\ArticleViewRecorderInterface;

/**
 * Records a public article view: schedules the async per-view counter
 * increment through the {@see ArticleViewRecorderInterface} — the page
 * response is never blocked by the write.
 */
final class RecordArticleViewUseCase
{
    public function __construct(
        private readonly ArticleViewRecorderInterface $views,
    ) {
    }

    public function record(int $articleId, string $ip, ?string $userAgent): void
    {
        $this->views->record($articleId, $ip, $userAgent);
    }
}
