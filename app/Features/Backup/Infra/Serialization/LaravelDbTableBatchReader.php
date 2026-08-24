<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Serialization;

use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * The query-builder batch reader (backup.md:254-270, 379): streams one
 * table's rows as arrays keyed by column name, in keyset-paginated
 * chunks of `$batchSize` ordered by the primary key (`id`, or `key`
 * for the `settings` table which has no `id` column). Keyset
 * pagination (not `offset`) keeps the export stable while rows change
 * concurrently and avoids the offset scan cost on large tables.
 */
final class LaravelDbTableBatchReader implements DbTableBatchReadable
{
    public function __construct(
        private readonly string $tableName,
        private readonly string $keyColumn = 'id',
    ) {
    }

    /**
     * @return Generator<array<string, mixed>>
     */
    public function readBatch(int $batchSize): Generator
    {
        $lastKey = null;

        do {
            $query = DB::table($this->tableName)
                ->orderBy($this->keyColumn)
                ->limit($batchSize);

            if ($lastKey !== null) {
                $query->where($this->keyColumn, '>', $lastKey);
            }

            $rows = $query->get();

            foreach ($rows as $row) {
                $lastKey = $row->{$this->keyColumn};
                yield (array) $row;
            }

            // The loop continues only while a full batch came back; a
            // short chunk (fewer rows than `$batchSize` — including a
            // table smaller than the batch) means it was the final one,
            // already fully yielded above.
        } while ($rows->count() === $batchSize);
    }
}
