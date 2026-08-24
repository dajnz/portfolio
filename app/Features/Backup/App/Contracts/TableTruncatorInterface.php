<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * The restore's table wipe (backup.md:401): truncates every given
 * table, child-first, `RESTART IDENTITY CASCADE`. An abstraction over
 * the query-builder truncation so the app-layer workflow never names
 * the DB — the implementation is responsible for rejecting any
 * identifier that is not a plain table name (the caller supplies
 * config-driven names only, never user input).
 */
interface TableTruncatorInterface
{
    /**
     * @param  list<string>  $tableNames
     */
    public function truncateAll(array $tableNames): void;
}
