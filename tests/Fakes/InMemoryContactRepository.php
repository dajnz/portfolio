<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * In-memory contact repository fake for unit tests.
 */
final class InMemoryContactRepository implements ContactRepositoryInterface
{
    /**
     * @var Contact[]
     */
    private array $items;

    /**
     * @var array<int, int[]>  id => ordered id list of the last reorder call
     */
    public array $reorders = [];

    /**
     * @param  Contact[]  $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @return Contact[]
     */
    public function findAll(): array
    {
        return $this->items;
    }

    public function findById(int $id): ?Contact
    {
        foreach ($this->items as $item) {
            if ($item->toId() === $id) {
                return $item;
            }
        }

        return null;
    }

    public function save(Contact $contact): void
    {
        foreach ($this->items as $index => $item) {
            if ($item->toId() === $contact->toId()) {
                $this->items[$index] = $contact;

                return;
            }
        }

        $this->items[] = $contact;
    }

    public function delete(int $id): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            fn (Contact $c): bool => $c->toId() !== $id,
        ));
    }

    public function reorder(array $ids): void
    {
        $this->reorders[] = $ids;
    }

    public function listQuery(): Builder
    {
        throw new \LogicException('Not supported by the in-memory fake.');
    }
}
