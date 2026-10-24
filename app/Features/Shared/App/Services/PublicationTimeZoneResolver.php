<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Services;

use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use DateTimeZone;
use Throwable;

/**
 * Resolves the public surface's display zone for publication dates —
 * the owner's `about_info.timezone`, validated against the IANA
 * database with a UTC fallback (the stored value is validated on write,
 * but legacy/manual rows must not break the blog queries).
 */
final class PublicationTimeZoneResolver
{
    private const FALLBACK = 'UTC';

    public function __construct(
        private readonly AboutInfoReadRepositoryInterface $aboutInfo,
    ) {
    }

    public function resolve(): string
    {
        $timeZone = $this->aboutInfo->findSiteTimezone();

        if ($timeZone === null || $timeZone === '') {
            return self::FALLBACK;
        }

        try {
            new DateTimeZone($timeZone);

            return $timeZone;
        } catch (Throwable) {
            return self::FALLBACK;
        }
    }
}
