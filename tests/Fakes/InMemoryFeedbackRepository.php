<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

final class InMemoryFeedbackRepository implements FeedbackRepositoryInterface
{
    /**
     * @var Feedback[]
     */
    private array $items = [];

    private ?Feedback $lastStored = null;

    /**
     * @param  Feedback[]  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function findById(int $id): ?Feedback
    {
        foreach ($this->items as $item) {
            if ($item->toId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function store(Feedback $feedback): void
    {
        $this->lastStored = $feedback;
        $this->items[] = $feedback;
    }

    public function markRead(int $id): void
    {
        $this->replace($id, fn (Feedback $f): Feedback => $f->withRead(true));
    }

    public function markForwarded(int $id): void
    {
        $this->replace($id, fn (Feedback $f): Feedback => $f->withForwarded(true));
    }

    public function delete(int $id): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            fn (Feedback $f): bool => $f->toId() !== $id,
        ));
    }

    public function listQuery(): Builder
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }

    public function lastStored(): ?Feedback
    {
        return $this->lastStored;
    }

    public function all(): array
    {
        return $this->items;
    }

    private function replace(int $id, callable $mutator): void
    {
        foreach ($this->items as $index => $item) {
            if ($item->toId() === $id) {
                $this->items[$index] = $mutator($item);
            }
        }
    }
}
