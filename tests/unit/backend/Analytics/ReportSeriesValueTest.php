<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use InvalidArgumentException;

/**
 * The series report result (B288): ordered chart points.
 */
describe('ReportSeriesValue', function (): void {
    it('holds ordered points', function (): void {
        $series = new ReportSeriesValue([
            ['label' => '2026-07-08', 'value' => 1],
            ['label' => '2026-07-09', 'value' => 2],
        ]);

        expect($series->toPoints())->toBe([
            ['label' => '2026-07-08', 'value' => 1],
            ['label' => '2026-07-09', 'value' => 2],
        ]);
    });

    it('allows an empty points list', function (): void {
        expect((new ReportSeriesValue([]))->toPoints())->toBe([]);
    });

    it('rejects malformed points', function (): void {
        expect(fn () => new ReportSeriesValue([['value' => 1]]))->toThrow(InvalidArgumentException::class, 'label')
            ->and(fn () => new ReportSeriesValue([['label' => 'x', 'value' => -1]]))->toThrow(InvalidArgumentException::class, 'value');
    });

    it('round-trips through its serialized form', function (): void {
        $series = new ReportSeriesValue([['label' => 'likes', 'value' => 2]]);

        expect(ReportSeriesValue::fromArray($series->toArray())->toPoints())->toBe($series->toPoints());
    });
});
