<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\RepositoryInterfaces;

use App\Features\Portfolio\Domain\Entities\Contact;
use Illuminate\Database\Eloquent\Builder;

/**
 * Persistence of the `contacts` aggregate (database.md:167-189)
 * including its translations.
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
     * Reorders contacts to the given zero-indexed positions (A4: the
     * array index becomes `sort_order`), atomically.
     *
     * @param  int[]  $ids  Contact IDs in drag order.
     */
    public function reorder(array $ids): void;

    /**
     * Base query for the admin cursor-paginated list (B11: query-builder
     * reads inside the feature's Infra layer), with the translations
     * eager-loaded for the list hydration.
     */
    public function listQuery(): Builder;
}
