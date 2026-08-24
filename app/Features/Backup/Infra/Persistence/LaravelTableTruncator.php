<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Persistence;

use App\Features\Backup\App\Contracts\TableTruncatorInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

use function preg_match;
use function sprintf;

/**
 * The query-builder table wipe (backup.md:401): `TRUNCATE ... RESTART
 * IDENTITY CASCADE` per table, child-first. The whitelist guard rejects
 * any identifier that is not a plain lowercase table name — the caller
 * supplies config-driven names only (never user input), and this check
 * makes the SQL-injection surface impossible even if a malicious name
 * ever reaches the service.
 */
final class LaravelTableTruncator implements TableTruncatorInterface
{
    /**
     * @param  list<string>  $tableNames
     */
    public function truncateAll(array $tableNames): void
    {
        foreach ($tableNames as $table) {
            if (! is_string($table) || preg_match('/^[a-z_]+$/', $table) !== 1) {
                throw new InvalidArgumentException(
                    sprintf('Refusing to truncate a non-table identifier [%s].', (string) $table),
                );
            }

            DB::statement(sprintf('TRUNCATE TABLE "%s" RESTART IDENTITY CASCADE', $table));
        }
    }
}
