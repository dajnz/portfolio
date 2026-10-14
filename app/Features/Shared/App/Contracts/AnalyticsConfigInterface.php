<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The analytics pipeline's configuration: the key for the HMAC-SHA256 digest
 * that anonymizes client IP addresses, and the regex matching the crawler
 * User-Agents whose batches are discarded. An abstraction over the framework's
 * `analytics.*` configuration so the app layer never reads framework
 * configuration; the framework-backed implementation lives on the infra layer.
 */
interface AnalyticsConfigInterface
{
    public function ipHashKey(): string;

    public function botUserAgentPattern(): string;
}
