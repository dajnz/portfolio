<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A chart result (B288): ordered points of `{label, value}` — e.g.
 * submissions per day or the like/dislike summary. Serialized as
 * `{points: [...]}`. The points list may be empty (no data).
 */
final class ReportSeriesValue
{
    /**
     * @param list<array{label: string, value: int|float}> $points
     */
    public function __construct(
        private readonly array $points,
    ) {
        foreach ($points as $point) {
            if (! array_key_exists('label', $point)
                || ! is_string($point['label'])
                || $point['label'] === '') {
                throw new InvalidArgumentException('Each series point must carry a non-empty string label.');
            }

            if (! array_key_exists('value', $point)
                || ! (is_int($point['value']) || is_float($point['value']))
                || $point['value'] < 0) {
                throw new InvalidArgumentException('Each series point value must be a non-negative number.');
            }
        }
    }

    /**
     * @return list<array{label: string, value: int|float}>
     */
    public function toPoints(): array
    {
        return $this->points;
    }

    /**
     * @param array{points: list<array{label: string, value: int|float}>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(points: $data['points'] ?? []);
    }

    /**
     * @return array{points: list<array{label: string, value: int|float}>}
     */
    public function toArray(): array
    {
        return [
            'points' => $this->points,
        ];
    }
}
