<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\RepositoryInterfaces;

use App\Features\Portfolio\Domain\Entities\Contact;

/**
 * Persistence of the `contacts` aggregate including its translations.
 */
interface ContactRepositoryInterface
{
    /**
     * All contacts ordered by `sort_order` ascending (landing page /
     * resume reads).
     *
     * @return Contact[]
     */
    public function findAll(): array;

    public function findById(int $id): ?Contact;

    public function save(Contact $contact): void;

    public function delete(int $id): void;

    /**
     * Reorders contacts to the given zero-indexed positions (the array
     * index becomes `sort_order`), atomically.
     *
     * @param  int[]  $ids  Contact IDs in drag order.
     */
    public function reorder(array $ids): void;
}
