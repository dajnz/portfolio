<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The admin list pagination request of a cursor-paginated query: the
 * `?page=` value, the raw `?cursor[...]` params and the optional
 * `?sort_column=`/`?sort_direction=` overrides. Parsing and
 * whitelist-validation happen in the infra paginator implementation.
 * Immutable.
 */
final readonly class PaginationQuery
{
    /**
     * @param  array<string, mixed>|null  $cursorParams  Raw `?cursor[...]` values.
     */
    public function __construct(
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
    ) {
    }
}
