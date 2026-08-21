<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use InvalidArgumentException;

/**
 * The scalar report result (B288): the current value plus an optional
 * previous-period value and the percentage change.
 */
describe('ReportScalarValue', function (): void {
    it('holds a value with an optional previous value', function (): void {
        $kpi = new ReportScalarValue(5, 4);

        expect($kpi->toValue())->toBe(5)
            ->and($kpi->toPrevious())->toBe(4);
    });

    it('computes the percentage change versus the previous value', function (): void {
        expect((new ReportScalarValue(90, 80))->toChangePercent())->toBe(12.5)
            ->and((new ReportScalarValue(5))->toChangePercent())->toBeNull()
            ->and((new ReportScalarValue(5, 0))->toChangePercent())->toBeNull()
            ->and((new ReportScalarValue(null, 5))->toChangePercent())->toBeNull();
    });

    it('rejects negative values', function (): void {
        expect(fn () => new ReportScalarValue(-1))->toThrow(InvalidArgumentException::class, 'value')
            ->and(fn () => new ReportScalarValue(1, -1))->toThrow(InvalidArgumentException::class, 'previous');
    });

    it('round-trips through its serialized form', function (): void {
        $kpi = new ReportScalarValue(20.0, 18);

        expect(ReportScalarValue::fromArray($kpi->toArray())->toValue())->toBe(20.0)
            ->and(ReportScalarValue::fromArray($kpi->toArray())->toPrevious())->toBe(18);
    });
});
