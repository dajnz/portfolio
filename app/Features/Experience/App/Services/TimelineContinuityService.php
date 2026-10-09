<?php

declare(strict_types=1);

namespace App\Features\Experience\App\Services;

use App\Features\Experience\Domain\Exceptions\TimelinePeriodConflictException;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use DateTimeImmutable;

/**
 * The ADR-0024 timeline-continuity rules (the "continuous career" model),
 * extracted from the duplicated private logic of the store/update use
 * cases: an item's period must not overlap its chronological
 * predecessor or successor (next `started_at` >= previous `ended_at`),
 * only the last item may be ongoing — when a new item is appended after
 * an ongoing predecessor, that predecessor's `ended_at` is auto-filled
 * with the new item's `started_at` (it was never explicitly closed).
 *
 * A domain requirement, living in the App layer per the ADR-0009 precedent
 * (services belong to `App/`, not `Domain/` — the rules stay pure
 * domain logic over the repository interface; only the exception
 * messages resolve translations).
 *
 * Callers must run the assertion inside their transaction — the
 * auto-fill write (`setEndedAt`) and the item's own save must land
 * atomically.
 */
final class TimelineContinuityService
{
    /**
     * @var callable(string): string
     */
    private $translator;

    /**
     * @param  callable(string): string|null  $translator  Message resolver —
     *                                                     the Laravel `__()`
     *                                                     by default; unit
     *                                                     tests inject a stub
     *                                                     (the app is not
     *                                                     booted there).
     */
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        ?callable $translator = null,
    ) {
        $this->translator = $translator ?? static fn (string $key): string => __($key);
    }

    /**
     * @throws TimelinePeriodConflictException  When the period breaks continuity.
     */
    public function assertContinuity(
        DateTimeImmutable $startedAt,
        ?DateTimeImmutable $endedAt,
        ?int $ignoreId,
    ): void {
        $predecessor = $this->repository->predecessorFor($startedAt, $ignoreId);

        if ($predecessor !== null) {
            $previousEnd = $predecessor->toPeriod()->toEndedAt();

            if ($previousEnd === null) {
                // Closing the ongoing predecessor at this item's start
                // must leave the predecessor's own period valid — an
                // item starting ON the predecessor's start day cannot
                // follow it (its end would equal its start): overlap.
                if ($startedAt <= $predecessor->toPeriod()->toStartedAt()) {
                    throw new TimelinePeriodConflictException(
                        'started_at',
                        $this->message('validation.experience.overlap_with_previous'),
                    );
                }

                $this->repository->setEndedAt($predecessor->toId(), $startedAt);
            } elseif ($startedAt < $previousEnd) {
                throw new TimelinePeriodConflictException(
                    'started_at',
                    $this->message('validation.experience.overlap_with_previous'),
                );
            }
        }

        $successor = $this->repository->successorFor($startedAt, $ignoreId);

        if ($successor !== null) {
            if ($endedAt === null) {
                throw new TimelinePeriodConflictException(
                    'ended_at',
                    $this->message('validation.experience.only_last_current'),
                );
            }

            if ($endedAt > $successor->toPeriod()->toStartedAt()) {
                throw new TimelinePeriodConflictException(
                    'ended_at',
                    $this->message('validation.experience.overlap_with_next'),
                );
            }
        }
    }

    private function message(string $key): string
    {
        return ($this->translator)($key);
    }
}
