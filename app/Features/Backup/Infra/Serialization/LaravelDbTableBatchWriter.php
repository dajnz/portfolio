<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Serialization;

use App\Features\Backup\App\Contracts\DbTableBatchWritable;
use Illuminate\Support\Facades\DB;

use function count;

/**
 * The query-builder batch writer (backup.md:272-285, 402): accumulates
 * the given rows and bulk-inserts them in chunks of 500 (one INSERT
 * statement per chunk). The `settings` variant (constructed with the
 * `upsertKey` of `key`) uses `upsert` by key instead — restore replaces
 * the settings row set via upsert-by-key (backup.md:402,441-444).
 */
final class LaravelDbTableBatchWriter implements DbTableBatchWritable
{
    /**
     * Rows per INSERT statement (backup.md:281).
     */
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly string $tableName,
        private readonly ?string $upsertKey = null,
    ) {
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function writeBatch(iterable $rows): void
    {
        $chunk = [];

        foreach ($rows as $row) {
            $chunk[] = $row;

            if (count($chunk) >= self::CHUNK_SIZE) {
                $this->insertChunk($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->insertChunk($chunk);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertChunk(array $rows): void
    {
        if (count($rows) === 0) {
            return;
        }

        $query = DB::table($this->tableName);

        if ($this->upsertKey !== null) {
            $query->upsert($rows, $this->upsertKey);
        } else {
            $query->insert($rows);
        }
    }
}
