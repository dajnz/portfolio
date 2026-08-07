<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;

/**
 * Reorders contacts to the zero-indexed positions of the submitted
 * `{ "ids": [...] }` payload (A4); the repository applies the writes
 * atomically.
 */
final class ReorderContactsUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  int[]  $ids  Contact IDs in the new drag order.
     */
    public function reorder(array $ids): void
    {
        $this->repository->reorder($ids);
    }
}
