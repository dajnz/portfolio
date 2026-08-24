<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use Generator;

/**
 * In-memory `DbTableBatchReadable` double for the unit suite: yields
 * the configured rows back verbatim.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
final class FakeBatchTableReader implements DbTableBatchReadable
{
    public function __construct(
        private readonly array $rows,
    ) {
    }

    /**
     * @return Generator<array<string, mixed>>
     */
    public function readBatch(int $batchSize): Generator
    {
        foreach ($this->rows as $row) {
            yield $row;
        }
    }
}
