<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `time_on_page` event: the seconds spent on the departing page
 * before navigation/unload/visibility change. The page context describes
 * the departing page (the frontend fires it on navigation start, before
 * the arriving page's context is set) — the seconds feed the overall
 * `avg_time_on_page` and the per-article average read time.
 */
final class TimeOnPageEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'time_on_page';

    public const MAX_SECONDS = 86400;

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly int $seconds,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if ($this->seconds < 0 || $this->seconds > self::MAX_SECONDS) {
            throw new InvalidArgumentException(
                sprintf('Seconds must be between 0 and %d.', self::MAX_SECONDS),
            );
        }
    }

    public function toSeconds(): int
    {
        return $this->seconds;
    }

    public function toEventSpecificData(): array
    {
        return [
            'seconds' => $this->seconds,
            'page_url' => $this->toPageUrl(),
        ];
    }
}
