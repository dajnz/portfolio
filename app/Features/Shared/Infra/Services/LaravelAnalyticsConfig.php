<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;
use Illuminate\Support\Facades\Config;

/**
 * The framework's `analytics.*` configuration behind the app-layer
 * {@see AnalyticsConfigInterface} contract.
 */
final class LaravelAnalyticsConfig implements AnalyticsConfigInterface
{
    public function ipHashKey(): string
    {
        return (string) Config::get('analytics.ip_hash_key', Config::get('app.key'));
    }

    public function botUserAgentPattern(): string
    {
        return (string) Config::get('analytics.not_bot_user_agent_pattern');
    }
}
