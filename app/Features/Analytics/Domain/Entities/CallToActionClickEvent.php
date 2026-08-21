<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;

/**
 * The plain CTA click events (analytics.md:19-22): `cta_click_github`,
 * `cta_click_demo`, `cta_click_cv`, `cta_click_feedback`. The frontend
 * sends no payload metadata for these — the target is carried by the
 * event type itself (B287), so `eventType()` derives it from the
 * target and `toEventSpecificData()` normalizes the stored payload.
 */
final class CallToActionClickEvent extends AnalyticsEvent
{
    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly CallToActionTarget $target,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);
    }

    public function eventType(): string
    {
        return 'cta_click_'.$this->target->value;
    }

    public function toTarget(): CallToActionTarget
    {
        return $this->target;
    }

    public function toEventSpecificData(): array
    {
        return [
            'target' => $this->target->value,
        ];
    }
}
