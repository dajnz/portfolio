<?php

declare(strict_types=1);

namespace Tests\Support\Backend;

use Illuminate\Support\Facades\DB;

final class JsonlTableFixture
{
    public const ROWS_TABLE = 'backup_jsonl_test';

    public const SETTINGS_TABLE = 'backup_settings_test';

    public static function createRowsTable(): void
    {
        DB::statement(
            'CREATE TEMP TABLE '.self::ROWS_TABLE
            .' (id serial primary key, name text, payload jsonb, created_at timestamp)',
        );
    }

    public static function createSettingsTable(): void
    {
        DB::statement(
            'CREATE TEMP TABLE '.self::SETTINGS_TABLE
            .' (key text primary key, value text, type text)',
        );
    }
}
