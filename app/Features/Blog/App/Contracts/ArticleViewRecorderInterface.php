<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Contracts;

/**
 * Records a public article view — the async per-view counter increment.
 * An abstraction over the infra `IncrementArticleViewJob` so the
 * app-layer use cases never name the queue class.
 */
interface ArticleViewRecorderInterface
{
    public function record(int $articleId, ?string $userAgent): void;
}
