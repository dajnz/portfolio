<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * The {@see CountCacheInvalidatorInterface} over the shared admin
 * cursor paginator — bumps the per-entity count-cache version so the
 * paginated list re-counts on the next request (B175).
 */
final class AdminListCountCacheInvalidator implements CountCacheInvalidatorInterface
{
    public function invalidate(string $entity): void
    {
        AdminCursorPaginator::invalidateCountCache($entity);
    }
}
