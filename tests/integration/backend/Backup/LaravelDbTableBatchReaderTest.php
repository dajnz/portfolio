<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchReader;
use Illuminate\Support\Facades\DB;
use Tests\Support\Backend\JsonlTableFixture;

beforeEach(function (): void {
    JsonlTableFixture::createRowsTable();
});

describe('LaravelDbTableBatchReader', function (): void {
    it('streams rows in key order across multiple batches', function (): void {
        $rows = [];

        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'name' => "row-{$i}",
                'payload' => null,
                'created_at' => '2026-07-09 02:00:00',
            ];
        }

        DB::table(JsonlTableFixture::ROWS_TABLE)->insert($rows);

        $read = iterator_to_array(
            (new LaravelDbTableBatchReader(JsonlTableFixture::ROWS_TABLE))->readBatch(2),
        );

        expect(array_column($read, 'id'))->toBe([1, 2, 3, 4, 5])
            ->and(array_column($read, 'name'))->toBe(['row-1', 'row-2', 'row-3', 'row-4', 'row-5']);
    });

    it('keeps composite-key tables stable across batch boundaries', function (): void {
        DB::statement(
            'CREATE TEMP TABLE backup_translation_test'
            .' (article_id integer, locale text, name text, primary key (article_id, locale))',
        );

        DB::table('backup_translation_test')->insert([
            ['article_id' => 1, 'locale' => 'en', 'name' => 'First EN'],
            ['article_id' => 1, 'locale' => 'ru', 'name' => 'First RU'],
            ['article_id' => 2, 'locale' => 'en', 'name' => 'Second EN'],
            ['article_id' => 2, 'locale' => 'ru', 'name' => 'Second RU'],
        ]);

        $read = iterator_to_array(
            (new LaravelDbTableBatchReader('backup_translation_test', ['article_id', 'locale']))->readBatch(2),
        );

        expect(array_column($read, 'name'))->toBe([
            'First EN',
            'First RU',
            'Second EN',
            'Second RU',
        ]);
    });
});
