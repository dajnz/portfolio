<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;

/**
 * Shared factory helpers for the analytics unit suites: the client
 * identifiers and the page context every typed event needs.
 */
final class AnalyticsEventFixture
{
    public static function clientIds(
        string $sessionId = 's-1',
        ?string $ip = 'ip-hash',
        ?string $userAgent = 'Mozilla/5.0 Chrome/126.0',
    ): ClientIds {
        return new ClientIds($sessionId, $ip, $userAgent);
    }

    public static function pageDetails(
        ?PageType $type = null,
        ?int $entityId = null,
        ?string $url = 'https://example.com/',
    ): PageDetails {
        return new PageDetails($type, $entityId, $url);
    }
}
