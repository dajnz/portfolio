<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A singular scalar/KPI result (B288): the current value plus an
 * optional previous-period value for comparison (trend). Serialized as
 * `{value, previous}` — `previous` is null when there is no prior
 * report of the same type.
 */
final class ReportScalarValue
{
    public function __construct(
        private readonly int|float|null $value,
        private readonly int|float|null $previous = null,
    ) {
        $this->assertNonNegative('value', $value);
        $this->assertNonNegative('previous', $previous);
    }

    public function toValue(): int|float|null
    {
        return $this->value;
    }

    public function toPrevious(): int|float|null
    {
        return $this->previous;
    }

    /**
     * The percentage change versus the previous value — null when
     * either side is missing or the previous is zero (division by
     * zero). Rounded to 1 decimal.
     */
    public function toChangePercent(): ?float
    {
        if ($this->value === null || $this->previous === null || $this->previous == 0) {
            return null;
        }

        return round(($this->value - $this->previous) / $this->previous * 100, 1);
    }

    /**
     * @param array{value: int|float|null, previous: int|float|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'] ?? null,
            previous: $data['previous'] ?? null,
        );
    }

    /**
     * @return array{value: int|float|null, previous: int|float|null}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'previous' => $this->previous,
        ];
    }

    private function assertNonNegative(string $name, int|float|null $value): void
    {
        if ($value !== null && $value < 0) {
            throw new InvalidArgumentException(
                sprintf('KPI %s must be a non-negative number or null.', $name),
            );
        }
    }
}
