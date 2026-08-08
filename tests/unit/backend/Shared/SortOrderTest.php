<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\SortOrder;

describe('SortOrder', function (): void {
    it('exposes the zero-based position', function (): void {
        $order = new SortOrder(3);

        expect($order->toValue())->toBe(3);
    });

    it('rejects a negative position', function (): void {
        new SortOrder(-1);
    })->throws(InvalidArgumentException::class);
});
