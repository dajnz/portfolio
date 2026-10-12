<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use DateTimeImmutable;

/**
 * In-memory timeline-item repository fake for unit tests. Implements
 * the continuity lookups with the same `(started_at, id)` tuple
 * semantics as the Eloquent repository: a new item (no
 * `$ignoreId`) follows every same-start item (it will hold the max id);
 * an update excludes itself.
 */
final class InMemoryTimelineItemRepository implements TimelineItemRepositoryInterface
{
    /**
     * @var TimelineItem[]
     */
    private array $items;

    /**
     * @var array<int, DateTimeImmutable>  id => ended_at of the last `setEndedAt` call
     */
    public array $endedAtWrites = [];

    /**
     * @param  TimelineItem[]  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function findById(int $id): ?TimelineItem
    {
        foreach ($this->items as $item) {
            if ($item->toId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function predecessorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem
    {
        return $this->nearest($startedAt, $ignoreId, before: true);
    }

    public function successorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem
    {
        return $this->nearest($startedAt, $ignoreId, before: false);
    }

    public function setEndedAt(int $id, DateTimeImmutable $endedAt): void
    {
        $this->endedAtWrites[$id] = $endedAt;

        // Close the stored item's period so later lookups see it as
        // ended (mirrors the Eloquent row update).
        foreach ($this->items as $index => $item) {
            if ($item->toId() === $id) {
                $this->items[$index] = new TimelineItem(
                    id: $id,
                    period: new WorkPeriod($item->toPeriod()->toStartedAt(), $endedAt),
                    isPublished: $item->toIsPublished(),
                    sortOrder: $item->toSortOrder(),
                    relatedProject: $item->toRelatedProject(),
                    includeInResume: $item->toIncludeInResume(),
                    translations: [],
                    techTags: $item->toTechTags(),
                );

                return;
            }
        }
    }

    public function setPublished(int $id, bool $published): void
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    public function delete(int $id): void
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    public function restore(int $id): void
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    public function forceDelete(int $id): void
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    public function save(TimelineItem $item): int
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    /**
     * The item with the greatest `(started_at, id)` tuple strictly
     * before the anchor (predecessor) or the smallest strictly after it
     * (successor). The anchor id is the ignored item's id, or +∞/−∞ for
     * a new item (dates compare at day granularity, mirroring the
     * `Y-m-d` column comparisons).
     */
    private function nearest(DateTimeImmutable $startedAt, ?int $ignoreId, bool $before): ?TimelineItem
    {
        $anchorDay = $startedAt->format('Y-m-d');
        $anchorId = $ignoreId ?? ($before ? PHP_INT_MAX : PHP_INT_MIN);
        $best = null;

        foreach ($this->items as $item) {
            if ($item->toId() === $ignoreId) {
                continue;
            }

            $itemDay = $item->toPeriod()->toStartedAt()->format('Y-m-d');

            $matches = $before
                ? ($itemDay < $anchorDay || ($itemDay === $anchorDay && $item->toId() < $anchorId))
                : ($itemDay > $anchorDay || ($itemDay === $anchorDay && $item->toId() > $anchorId));

            if (! $matches) {
                continue;
            }

            if ($best === null) {
                $best = $item;

                continue;
            }

            $bestDay = $best->toPeriod()->toStartedAt()->format('Y-m-d');
            $isCloser = $before
                ? ($itemDay > $bestDay || ($itemDay === $bestDay && $item->toId() > $best->toId()))
                : ($itemDay < $bestDay || ($itemDay === $bestDay && $item->toId() < $best->toId()));

            if ($isCloser) {
                $best = $item;
            }
        }

        return $best;
    }
}
