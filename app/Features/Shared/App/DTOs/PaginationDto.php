<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The length-aware pagination facts of a page-numbered list (the admin
 * autocomplete variants): the requested page, the per-page size and the
 * derived bounds (`from`/`to`/`last_page` over the total). The controller
 * maps these into the wire `meta` payload.
 */
final readonly class PaginationDto
{
    public function __construct(
        public int $currentPage,
        public ?int $from,
        public ?int $to,
        public int $perPage,
        public int $lastPage,
        public int $total,
    ) {
    }
}
