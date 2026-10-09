<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `page_view` event: every public page load, with the referrer and
 * the document title. The title is informational (not aggregated) —
 * nullable so a malformed event still counts towards the page-view
 * metrics.
 */
final class PageViewEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'page_view';

    public const MAX_REFERRER_LENGTH = 2048;

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly ?string $referrer,
        private readonly ?string $title,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if ($this->referrer !== null && mb_strlen($this->referrer) > self::MAX_REFERRER_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Referrer must not exceed %d characters.', self::MAX_REFERRER_LENGTH),
            );
        }
    }

    public function toReferrer(): ?string
    {
        return $this->referrer;
    }

    public function toTitle(): ?string
    {
        return $this->title;
    }

    public function toEventSpecificData(): array
    {
        return [
            'referrer' => $this->referrer,
            'title' => $this->title,
        ];
    }
}
