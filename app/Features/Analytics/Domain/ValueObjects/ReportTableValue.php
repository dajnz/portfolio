<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A tabular result (B288): named columns in display order plus rows
 * keyed by column — e.g. the top articles or the CTA click breakdown.
 * Serialized as `{columns: [...], rows: [{column: value, ...}]}`. The
 * rows list may be empty (no data); every row must carry every column.
 */
final class ReportTableValue
{
    /**
     * @param list<string> $columns
     * @param list<array<string, int|float|string|null>> $rows
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
    ) {
        if ($columns === []) {
            throw new InvalidArgumentException('Table columns must not be empty.');
        }

        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                throw new InvalidArgumentException('Each table column must be a non-empty string.');
            }
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each table row must be an array.');
            }

            foreach ($columns as $column) {
                if (! array_key_exists($column, $row)
                    || ($row[$column] !== null
                        && ! is_int($row[$column])
                        && ! is_float($row[$column])
                        && ! is_string($row[$column]))) {
                    throw new InvalidArgumentException(
                        sprintf('Table row is missing a valid value for column [%s].', $column),
                    );
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    public function toColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array<string, int|float|string|null>>
     */
    public function toRows(): array
    {
        return $this->rows;
    }

    /**
     * @param array{columns: list<string>, rows: list<array<string, int|float|string|null>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            columns: $data['columns'] ?? [],
            rows: $data['rows'] ?? [],
        );
    }

    /**
     * @return array{columns: list<string>, rows: list<array<string, int|float|string|null>>}
     */
    public function toArray(): array
    {
        return [
            'columns' => $this->columns,
            'rows' => $this->rows,
        ];
    }
}
