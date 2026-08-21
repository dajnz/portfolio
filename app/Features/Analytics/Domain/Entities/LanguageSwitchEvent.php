<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `cta_click_language` event (analytics.md:23): the public
 * language switch, carrying the selected locale. The spec fixes the
 * payload to `ru|en` — the whitelist tracks the supported-locale
 * contract of the event itself.
 */
final class LanguageSwitchEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'cta_click_language';

    public const LOCALES = ['en', 'ru'];

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly string $locale,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if (! in_array($this->locale, self::LOCALES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Locale must be one of: %s.',
                    implode(', ', self::LOCALES),
                ),
            );
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toEventSpecificData(): array
    {
        return [
            'target' => 'language',
            'locale' => $this->locale,
        ];
    }
}
