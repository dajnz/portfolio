<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

use DateTimeImmutable;

/**
 * Hard-deletes one entity type's items soft-deleted before the cutoff and
 * returns the number of rows removed. Implementations live on the Infra
 * layer, one per stored entity family.
 */
interface TrashedItemPrunerInterface
{
    public function prune(DateTimeImmutable $cutoff): int;
}
