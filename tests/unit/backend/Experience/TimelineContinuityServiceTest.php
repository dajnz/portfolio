<?php

declare(strict_types=1);

use App\Features\Experience\App\Services\TimelineContinuityService;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Exceptions\TimelinePeriodConflictException;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use Tests\Fakes\InMemoryTimelineItemRepository;

/**
 * A timeline item carrying only what the continuity rules read — the
 * period and the id (translations/tags are irrelevant to the rules).
 */
function continuityItem(int $id, string $start, ?string $end = null): TimelineItem
{
    return new TimelineItem(
        id: $id,
        period: new WorkPeriod(
            new DateTimeImmutable($start),
            $end === null ? null : new DateTimeImmutable($end),
        ),
        isPublished: true,
        sortOrder: new SortOrder(0),
        relatedProject: null,
        includeInResume: false,
        translations: [],
    );
}

/**
 * @return array{0: InMemoryTimelineItemRepository, 1: TimelineContinuityService}
 */
function continuityService(array $items): array
{
    $repository = new InMemoryTimelineItemRepository($items);

    // Unit tests run framework-free — inject a stub translator instead
    // of the Laravel `__()` default.
    $service = new TimelineContinuityService(
        $repository,
        static fn (string $key): string => $key,
    );

    return [$repository, $service];
}

function expectContinuityRejected(TimelineContinuityService $service, string $field, string $start, ?string $end = null): void
{
    try {
        $service->assertContinuity(
            new DateTimeImmutable($start),
            $end === null ? null : new DateTimeImmutable($end),
            null,
        );
        test()->fail('Expected a TimelinePeriodConflictException.');
    } catch (TimelinePeriodConflictException $e) {
        expect($e->toField())->toBe($field);
    }
}

describe('TimelineContinuityService', function (): void {
    it('allows a period starting after a closed predecessor', function (): void {
        [$repository, $service] = continuityService([continuityItem(1, '2020-01-01', '2020-12-31')]);

        $service->assertContinuity(
            new DateTimeImmutable('2021-03-01'),
            new DateTimeImmutable('2021-09-30'),
            null,
        );

        expect($repository->endedAtWrites)->toBeEmpty();
    });

    it('rejects a period overlapping its closed predecessor', function (): void {
        [, $service] = continuityService([continuityItem(1, '2020-01-01', '2020-12-31')]);

        expectContinuityRejected($service, 'started_at', '2020-06-01', '2021-01-31');
    });

    it("rejects starting on an ongoing predecessor's start day", function (): void {
        [, $service] = continuityService([continuityItem(1, '2020-01-01')]);

        expectContinuityRejected($service, 'started_at', '2020-01-01', null);
    });

    it("auto-closes an ongoing predecessor at the new item's start", function (): void {
        [$repository, $service] = continuityService([continuityItem(1, '2020-01-01')]);

        $service->assertContinuity(
            new DateTimeImmutable('2020-06-01'),
            new DateTimeImmutable('2021-01-31'),
            null,
        );

        expect($repository->endedAtWrites)->toHaveKey(1)
            ->and($repository->endedAtWrites[1])->toEqual(new DateTimeImmutable('2020-06-01'));
    });

    it('rejects a period overlapping its successor', function (): void {
        [, $service] = continuityService([continuityItem(2, '2021-01-01', '2021-06-30')]);

        expectContinuityRejected($service, 'ended_at', '2020-06-01', '2021-02-01');
    });

    it('rejects an ongoing period when a successor exists (only the last item may be ongoing)', function (): void {
        [, $service] = continuityService([continuityItem(2, '2021-01-01', '2021-06-30')]);

        expectContinuityRejected($service, 'ended_at', '2020-06-01', null);
    });

    it('excludes the item itself from the lookups via ignoreId', function (): void {
        [$repository, $service] = continuityService([continuityItem(1, '2020-01-01', '2020-12-31')]);

        // The item's own period spans the checked date — without the
        // ignore, it would act as its own predecessor and successor.
        $service->assertContinuity(new DateTimeImmutable('2020-06-01'), null, 1);

        expect($repository->endedAtWrites)->toBeEmpty();
    });

    it('chains same-start items by id for a new item (ADR-0024 tuple ordering)', function (): void {
        [, $service] = continuityService([
            continuityItem(1, '2020-01-01', '2020-01-15'),
            continuityItem(2, '2020-01-01', '2020-02-15'),
        ]);

        // A new item starting on the shared day follows the highest-id
        // same-start item (id 2) — which ends AFTER the new item's
        // start, so the period overlaps it.
        expectContinuityRejected($service, 'started_at', '2020-01-01', '2020-03-01');
    });
});
