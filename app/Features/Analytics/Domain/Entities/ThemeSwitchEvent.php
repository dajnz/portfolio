<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `cta_click_theme` event: the theme switch. The frontend sends no
 * payload for it — the mode is modelled as nullable for the `light|dark`
 * payload and validated when present.
 */
final class ThemeSwitchEvent extends AnalyticsEvent
{
    public const EVENT_TYPE = 'cta_click_theme';

    public const MODES = ['light', 'dark'];

    public function __construct(
        int $id,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        private readonly ?string $mode,
        DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $clientIds, $pageDetails, $createdAt);

        if ($this->mode !== null && ! in_array($this->mode, self::MODES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Mode must be one of: %s.',
                    implode(', ', self::MODES),
                ),
            );
        }
    }

    public function toMode(): ?string
    {
        return $this->mode;
    }

    public function toEventSpecificData(): array
    {
        return [
            'target' => 'theme',
            'mode' => $this->mode,
        ];
    }
}
