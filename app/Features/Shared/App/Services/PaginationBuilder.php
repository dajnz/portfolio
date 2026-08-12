<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Services;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * Builds the pagination facts of the page-numbered lists — the
 * `PaginationDto` the controllers map into the wire `meta` payloads of
 * the JSON autocomplete variants (admin-section-ui.md:539-572 and
 * routes.md:199-212).
 */
final class PaginationBuilder
{
    /**
     * `{current_page, from, to, per_page, last_page, total}` — `from`
     * is null on an empty dataset.
     */
    public function lengthAware(int $page, int $perPage, int $total, int $rowsCount): PaginationDto
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? null : ($page - 1) * $perPage + 1;

        return new PaginationDto(
            currentPage: $page,
            from: $from,
            to: $from === null ? null : $from + $rowsCount - 1,
            perPage: $perPage,
            lastPage: $lastPage,
            total: $total,
        );
    }
}
