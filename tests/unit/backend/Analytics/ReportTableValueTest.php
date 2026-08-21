<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use InvalidArgumentException;

/**
 * The table report result (B288): ordered columns plus column-keyed
 * rows.
 */
describe('ReportTableValue', function (): void {
    it('holds ordered columns and column-keyed rows', function (): void {
        $table = new ReportTableValue(['id', 'title'], [
            ['id' => 1, 'title' => 'Payments'],
        ]);

        expect($table->toColumns())->toBe(['id', 'title'])
            ->and($table->toRows())->toBe([['id' => 1, 'title' => 'Payments']]);
    });

    it('allows an empty rows list', function (): void {
        expect((new ReportTableValue(['id'], []))->toRows())->toBe([]);
    });

    it('rejects empty columns and rows missing column values', function (): void {
        expect(fn () => new ReportTableValue([], []))->toThrow(InvalidArgumentException::class, 'columns')
            ->and(fn () => new ReportTableValue(['id', 'title'], [['id' => 1]]))->toThrow(InvalidArgumentException::class, 'title');
    });

    it('round-trips through its serialized form', function (): void {
        $table = new ReportTableValue(['id', 'views'], [['id' => 1, 'views' => 3]]);

        $restored = ReportTableValue::fromArray($table->toArray());

        expect($restored->toColumns())->toBe($table->toColumns())
            ->and($restored->toRows())->toBe($table->toRows());
    });
});
