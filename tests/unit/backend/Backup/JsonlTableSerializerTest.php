<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Infra\Serialization\JsonlTableSerializer;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchReader;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchWriter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The JSONL table serializer (backup.md:287-306): the per-table
 * export/import surface of the archive's `data/` files. The round-trip
 * runs against a real PostgreSQL temp table (the suite's DB contract —
 * backup.md:79: one `json_encode($row, JSON_UNESCAPED_UNICODE)` object
 * per line) through the query-builder reader/writer, including the
 * `settings` upsert-by-key variant (backup.md:402).
 */
function backupJsonlFixtureTable(): void
{
    DB::statement('CREATE TEMP TABLE backup_jsonl_test (id serial primary key, name text, payload jsonb, created_at timestamp)');
}

beforeEach(function (): void {
    backupJsonlFixtureTable();
});

describe('JsonlTableSerializer', function (): void {
    it('exports one JSON object per line with unicode preserved', function (): void {
        DB::table('backup_jsonl_test')->insert([
            'name' => 'Привет мир',
            'payload' => json_encode(['a' => 1], JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-09 02:00:00',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        (new JsonlTableSerializer('backup_jsonl_test'))->writeToFile(
            new LaravelDbTableBatchReader('backup_jsonl_test'),
            $path,
        );

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        @unlink($path);

        expect($lines)->toHaveCount(1)
            ->and($lines[0])->toBe(
                '{"id":1,"name":"Привет мир","payload":"{\"a\": 1}","created_at":"2026-07-09 02:00:00"}',
            );
    });

    it('exports a table to a JSONL file', function (): void {
        $rows = [];

        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'name' => "row-{$i}",
                'payload' => json_encode(['n' => $i], JSON_THROW_ON_ERROR),
                'created_at' => "2026-07-0{$i} 02:00:00",
            ];
        }

        (new LaravelDbTableBatchWriter('backup_jsonl_test'))->writeBatch($rows);

        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        (new JsonlTableSerializer('backup_jsonl_test'))->writeToFile(
            new LaravelDbTableBatchReader('backup_jsonl_test'),
            $path,
        );

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        @unlink($path);

        expect($lines)->toHaveCount(5)
            ->and($lines[0])->toBe(
                '{"id":1,"name":"row-1","payload":"{\"n\": 1}","created_at":"2026-07-01 02:00:00"}',
            )
            ->and($lines[4])->toBe(
                '{"id":5,"name":"row-5","payload":"{\"n\": 5}","created_at":"2026-07-05 02:00:00"}',
            );
    });

    it('imports a JSONL file back into a table', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'jsonl-');
        file_put_contents($path, implode("\n", [
            '{"id":1,"name":"row-1","payload":"{\"n\": 1}","created_at":"2026-07-01 02:00:00"}',
            '{"id":2,"name":"row-2","payload":"{\"n\": 2}","created_at":"2026-07-02 02:00:00"}',
            '{"id":3,"name":"row-3","payload":"{\"n\": 3}","created_at":"2026-07-03 02:00:00"}',
            '{"id":4,"name":"row-4","payload":"{\"n\": 4}","created_at":"2026-07-04 02:00:00"}',
            '{"id":5,"name":"row-5","payload":"{\"n\": 5}","created_at":"2026-07-05 02:00:00"}',
        ]));

        (new JsonlTableSerializer('backup_jsonl_test'))->readFromFile(
            $path,
            new LaravelDbTableBatchWriter('backup_jsonl_test'),
        );

        @unlink($path);

        $restored = DB::table('backup_jsonl_test')->orderBy('id')->get();

        expect($restored)->toHaveCount(5)
            ->and((string) $restored[0]->name)->toBe('row-1')
            ->and((string) $restored[4]->name)->toBe('row-5')
            ->and(json_decode((string) $restored[2]->payload, true))->toBe(['n' => 3]);
    });

    it('chunks large batches across multiple writeBatch calls', function (): void {
        $rows = [];

        for ($i = 1; $i <= 1100; $i++) {
            $rows[] = [
                'name' => "row-{$i}",
                'payload' => null,
                'created_at' => '2026-07-09 02:00:00',
            ];
        }

        $writer = new LaravelDbTableBatchWriter('backup_jsonl_test');
        $writer->writeBatch($rows);

        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        (new JsonlTableSerializer('backup_jsonl_test'))->writeToFile(
            new LaravelDbTableBatchReader('backup_jsonl_test'),
            $path,
        );

        $lines = count(file($path, FILE_IGNORE_NEW_LINES));
        @unlink($path);

        expect(DB::table('backup_jsonl_test')->count())->toBe(1100)
            ->and($lines)->toBe(1100);
    });

    it('upserts the settings variant by key', function (): void {
        DB::statement('CREATE TEMP TABLE backup_settings_test (key text primary key, value text, type text)');

        $writer = new LaravelDbTableBatchWriter('backup_settings_test', 'key');

        $writer->writeBatch([
            ['key' => 'site_title', 'value' => 'Portfolio', 'type' => 'string'],
            ['key' => 'items_per_page', 'value' => '12', 'type' => 'integer'],
        ]);
        $writer->writeBatch([
            ['key' => 'site_title', 'value' => 'Renamed', 'type' => 'string'],
        ]);

        expect(DB::table('backup_settings_test')->count())->toBe(2)
            ->and(DB::table('backup_settings_test')->where('key', 'site_title')->value('value'))->toBe('Renamed')
            ->and(DB::table('backup_settings_test')->where('key', 'items_per_page')->value('value'))->toBe('12');
    });

    it('reads an empty file as a no-op import', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        try {
            (new JsonlTableSerializer('backup_jsonl_test'))->readFromFile(
                $path,
                new LaravelDbTableBatchWriter('backup_jsonl_test'),
            );
        } finally {
            @unlink($path);
        }

        expect(DB::table('backup_jsonl_test')->count())->toBe(0);
    });
});
