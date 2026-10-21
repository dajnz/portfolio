<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleViewRepositoryInterface;
use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;

/**
 * Records a stored article view: known crawlers and requests without a
 * User-Agent are skipped, everything else increments the durable counter.
 */
final class IncrementArticleViewUseCase
{
    public function __construct(
        private readonly ArticleViewRepositoryInterface $views,
        private readonly AnalyticsConfigInterface $analyticsConfig,
    ) {
    }

    public function increment(int $articleId, ?string $userAgent): void
    {
        if (! $this->isHumanVisit($userAgent)) {
            return;
        }

        $this->views->increment($articleId);
    }

    private function isHumanVisit(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return false;
        }

        return preg_match($this->analyticsConfig->botUserAgentPattern(), $userAgent) !== 1;
    }
}
