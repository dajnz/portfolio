<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Invalidates the shared admin list count cache after a write to a
 * cursor-paginated entity (the 30 s per-filter-combo totals of
 * admin-section-ui.md:149, versioned per entity — B175). An abstraction
 * over {@see \App\Features\Shared\Infra\Services\AdminCursorPaginator::invalidateCountCache()}
 * so the app layer never touches the infra paginator; the
 * framework-backed implementation lives on the infra layer.
 */
interface CountCacheInvalidatorInterface
{
    public function invalidate(string $entity): void;
}
