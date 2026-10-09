<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Serialization;

use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use Generator;
use Illuminate\Support\Facades\DB;

use function is_array;

/**
 * The query-builder batch reader: streams one table's rows as arrays
 * keyed by column name, in keyset-paginated chunks of `$batchSize`
 * ordered by the key column(s) — `id` by default, `key` for the
 * `settings` table, and the composite primary-key columns for the
 * translation/pivot tables that have no `id` (the row-value comparison
 * keeps the keyset stable under duplicate first-column values). Keyset
 * pagination (not `offset`) keeps the export stable while rows change
 * concurrently and avoids the offset scan cost on large tables.
 */
final class LaravelDbTableBatchReader implements DbTableBatchReadable
{
    /**
     * @param  string|list<string>  $keyColumns
     */
    public function __construct(
        private readonly string $tableName,
        private readonly string|array $keyColumns = 'id',
    ) {
    }

    /**
     * @return Generator<array<string, mixed>>
     */
    public function readBatch(int $batchSize): Generator
    {
        $keys = is_array($this->keyColumns) ? $this->keyColumns : [$this->keyColumns];
        $lastKey = null;

        do {
            $query = DB::table($this->tableName);

            foreach ($keys as $key) {
                $query->orderBy($key);
            }

            $query->limit($batchSize);

            if ($lastKey !== null) {
                $query->whereRowValues($keys, '>', $lastKey);
            }

            $rows = $query->get();

            foreach ($rows as $row) {
                $lastKey = array_map(static fn (string $key): mixed => $row->{$key}, $keys);
                yield (array) $row;
            }

            // The loop continues only while a full batch came back; a
            // short chunk (fewer rows than `$batchSize` — including a
            // table smaller than the batch) means it was the final one,
            // already fully yielded above.
        } while ($rows->count() === $batchSize);
    }
}
