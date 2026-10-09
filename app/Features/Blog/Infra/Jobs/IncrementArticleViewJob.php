<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * The queued per-view counter increment: dispatched by the public
 * article controller with the client IP and User-Agent. The
 * User-Agent is checked against the analytics bot blocklist
 * (`config('analytics.not_bot_user_agent_pattern')`) — known crawlers
 * and requests without a User-Agent are silently skipped, no
 * increment. The increment itself is a single atomic upsert
 * statement: the first view inserts the row, concurrent requests
 * conflict on the `article_id` primary key and increment —
 * `updateOrCreate + increment` would not be atomic under concurrent
 * first views.
 */
final class IncrementArticleViewJob implements ShouldQueue
{
    public function __construct(
        public readonly int $articleId,
        public readonly string $ip,
        public readonly ?string $userAgent,
    ) {
    }

    public function handle(): void
    {
        $userAgent = $this->userAgent;

        if ($userAgent === null
            || $userAgent === ''
            || preg_match((string) Config::get('analytics.not_bot_user_agent_pattern'), $userAgent)
        ) {
            return;
        }

        DB::statement(
            'INSERT INTO article_views (article_id, views) VALUES (?, 1)'
            .' ON CONFLICT (article_id) DO UPDATE SET views = article_views.views + 1',
            [$this->articleId],
        );
    }
}
