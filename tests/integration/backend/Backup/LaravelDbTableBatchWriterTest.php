<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchWriter;
use Illuminate\Support\Facades\DB;
use Tests\Support\Backend\JsonlTableFixture;

beforeEach(function (): void {
    JsonlTableFixture::createRowsTable();
});

describe('LaravelDbTableBatchWriter', function (): void {
    it('writes a large batch across multiple insert statements', function (): void {
        $rows = [];

        for ($i = 1; $i <= 1100; $i++) {
            $rows[] = [
                'name' => "row-{$i}",
                'payload' => null,
                'created_at' => '2026-07-09 02:00:00',
            ];
        }

        (new LaravelDbTableBatchWriter(JsonlTableFixture::ROWS_TABLE))->writeBatch($rows);

        expect(DB::table(JsonlTableFixture::ROWS_TABLE)->count())->toBe(1100)
            ->and(DB::table(JsonlTableFixture::ROWS_TABLE)->orderBy('id')->value('name'))->toBe('row-1');
    });

    it('upserts the settings variant by key', function (): void {
        JsonlTableFixture::createSettingsTable();

        $writer = new LaravelDbTableBatchWriter(JsonlTableFixture::SETTINGS_TABLE, 'key');

        $writer->writeBatch([
            ['key' => 'site_title', 'value' => 'Portfolio', 'type' => 'string'],
            ['key' => 'items_per_page', 'value' => '12', 'type' => 'integer'],
        ]);
        $writer->writeBatch([
            ['key' => 'site_title', 'value' => 'Renamed', 'type' => 'string'],
        ]);

        expect(DB::table(JsonlTableFixture::SETTINGS_TABLE)->count())->toBe(2)
            ->and(DB::table(JsonlTableFixture::SETTINGS_TABLE)->where('key', 'site_title')->value('value'))
            ->toBe('Renamed')
            ->and(DB::table(JsonlTableFixture::SETTINGS_TABLE)->where('key', 'items_per_page')->value('value'))
            ->toBe('12');
    });
});
