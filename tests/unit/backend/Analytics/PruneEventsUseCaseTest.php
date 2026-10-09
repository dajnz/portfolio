<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\PruneEventsUseCase;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsEventRepository;

describe('PruneEventsUseCase', function (): void {
    it('deletes events older than the retention window', function (): void {
        $repository = new FakeAnalyticsEventRepository(deletedCount: 7);
        $useCase = new PruneEventsUseCase($repository);

        $deleted = $useCase->prune(90, new DateTimeImmutable('2026-07-15 12:00:00'));

        expect($deleted)->toBe(7)
            ->and($repository->deletedCutoffs)->toHaveCount(1)
            ->and($repository->deletedCutoffs[0]->format('Y-m-d H:i:s'))->toBe('2026-04-16 12:00:00');
    });

    it('returns zero when nothing matched', function (): void {
        $repository = new FakeAnalyticsEventRepository();
        $useCase = new PruneEventsUseCase($repository);

        $deleted = $useCase->prune(90, new DateTimeImmutable('2026-07-15 12:00:00'));

        expect($deleted)->toBe(0)
            ->and($repository->deletedCutoffs)->toHaveCount(1);
    });

    it('defaults the cutoff to the current time', function (): void {
        $repository = new FakeAnalyticsEventRepository();
        $useCase = new PruneEventsUseCase($repository);

        $before = (new DateTimeImmutable())->modify('-90 days')->modify('-1 second');
        $useCase->prune(90);
        $after = (new DateTimeImmutable())->modify('-90 days');

        expect($repository->deletedCutoffs)->toHaveCount(1)
            ->and($repository->deletedCutoffs[0]->format('U'))->toBeGreaterThanOrEqual($before->format('U'))
            ->and($repository->deletedCutoffs[0]->format('U'))->toBeLessThanOrEqual($after->format('U'));
    });
});
