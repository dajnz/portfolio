<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Infra\Serialization\JsonlTableSerializer;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchReader;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchWriter;
use Illuminate\Support\Facades\DB;
use Tests\Support\Backend\JsonlTableFixture;

beforeEach(function (): void {
    JsonlTableFixture::createRowsTable();
});

describe('JsonlTableSerializer', function (): void {
    it('exports one JSON object per line with unicode preserved', function (): void {
        DB::table(JsonlTableFixture::ROWS_TABLE)->insert([
            'name' => 'Привет мир',
            'payload' => json_encode(['a' => 1], JSON_THROW_ON_ERROR),
            'created_at' => '2026-07-09 02:00:00',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        (new JsonlTableSerializer(JsonlTableFixture::ROWS_TABLE))->writeToFile(
            new LaravelDbTableBatchReader(JsonlTableFixture::ROWS_TABLE),
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

        (new LaravelDbTableBatchWriter(JsonlTableFixture::ROWS_TABLE))->writeBatch($rows);

        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        (new JsonlTableSerializer(JsonlTableFixture::ROWS_TABLE))->writeToFile(
            new LaravelDbTableBatchReader(JsonlTableFixture::ROWS_TABLE),
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

        (new JsonlTableSerializer(JsonlTableFixture::ROWS_TABLE))->readFromFile(
            $path,
            new LaravelDbTableBatchWriter(JsonlTableFixture::ROWS_TABLE),
        );

        @unlink($path);

        $restored = DB::table(JsonlTableFixture::ROWS_TABLE)->orderBy('id')->get();

        expect($restored)->toHaveCount(5)
            ->and((string) $restored[0]->name)->toBe('row-1')
            ->and((string) $restored[4]->name)->toBe('row-5')
            ->and(json_decode((string) $restored[2]->payload, true))->toBe(['n' => 3]);
    });

    it('reads an empty file as a no-op import', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'jsonl-');

        try {
            (new JsonlTableSerializer(JsonlTableFixture::ROWS_TABLE))->readFromFile(
                $path,
                new LaravelDbTableBatchWriter(JsonlTableFixture::ROWS_TABLE),
            );
        } finally {
            @unlink($path);
        }

        expect(DB::table(JsonlTableFixture::ROWS_TABLE)->count())->toBe(0);
    });
});
