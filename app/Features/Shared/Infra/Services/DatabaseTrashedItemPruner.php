<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\TrashedItemPrunerInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Chunked query-builder hard delete for a soft-deleting table. The table
 * name is supplied at wiring time, so the same adapter serves the blog
 * and experience entities whose rows carry no dependent files.
 */
final class DatabaseTrashedItemPruner implements TrashedItemPrunerInterface
{
    private const CHUNK_SIZE = 50;

    public function __construct(
        private readonly string $table,
    ) {
    }

    public function prune(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;

        DB::table($this->table)
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use (&$deleted): void {
                $ids = $rows->pluck('id')->all();

                DB::table($this->table)->whereIn('id', $ids)->delete();

                $deleted += count($ids);
            });

        return $deleted;
    }
}
