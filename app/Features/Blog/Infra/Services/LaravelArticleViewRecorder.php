<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Services;

use App\Features\Blog\App\Contracts\ArticleViewRecorderInterface;
use App\Features\Blog\Infra\Jobs\IncrementArticleViewJob;
use Illuminate\Support\Facades\Bus;

/**
 * The queue-backed recorder behind the
 * {@see ArticleViewRecorderInterface}: dispatches the
 * `IncrementArticleViewJob`.
 */
final class LaravelArticleViewRecorder implements ArticleViewRecorderInterface
{
    public function record(int $articleId, string $ip, ?string $userAgent): void
    {
        Bus::dispatch(new IncrementArticleViewJob(
            articleId: $articleId,
            ip: $ip,
            userAgent: $userAgent,
        ));
    }
}
