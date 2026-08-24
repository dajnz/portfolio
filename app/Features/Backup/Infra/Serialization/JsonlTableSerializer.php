<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Serialization;

use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use App\Features\Backup\App\Contracts\DbTableBatchWritable;
use App\Features\Backup\App\Contracts\DbTableExportable;
use App\Features\Backup\App\Contracts\DbTableImportable;
use RuntimeException;

use function count;
use function fclose;
use function fgets;
use function fopen;
use function fwrite;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_UNICODE;

/**
 * The JSONL table serializer (backup.md:287-306, 379, 402): the
 * per-table export/import surface of the archive's `data/` files —
 * one `json_encode($row, JSON_UNESCAPED_UNICODE)` object per line
 * (no enclosing array, no pretty-printing), `\n`-terminated. Export
 * consumes the {@see DbTableBatchReadable} source in batches and
 * appends each row immediately; import reads `fgets`-line by line,
 * accumulates batches and feeds them into the {@see DbTableBatchWritable}
 * destination (the JSONL import path, backup.md:285-303).
 */
final class JsonlTableSerializer implements DbTableExportable, DbTableImportable
{
    /**
     * Rows accumulated before a `writeBatch()` call (backup.md:281).
     */
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly string $tableName,
    ) {
    }

    public function writeToFile(DbTableBatchReadable $source, string $path): int
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open the JSONL export file for '{$this->tableName}' at '{$path}'.");
        }

        $rows = 0;

        try {
            foreach ($source->readBatch(self::BATCH_SIZE) as $row) {
                $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if (fwrite($handle, $line."\n") === false) {
                    throw new RuntimeException("Unable to write the JSONL export file for '{$this->tableName}'.");
                }

                $rows++;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    public function readFromFile(string $path, DbTableBatchWritable $dest): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open the JSONL import file for '{$this->tableName}' at '{$path}'.");
        }

        $batch = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $batch[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                if (count($batch) >= self::BATCH_SIZE) {
                    $dest->writeBatch($batch);
                    $batch = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if ($batch !== []) {
            $dest->writeBatch($batch);
        }
    }
}
