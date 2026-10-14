<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The configured default page sizes for the content lists, used as the
 * fallback when the admin has not overridden a page size in the `settings`
 * table. An abstraction over the committed `config('portfolio.*_per_page')`
 * values so the app layer never reads framework configuration; the
 * framework-backed implementation lives on the infra layer.
 */
interface PaginationDefaultsInterface
{
    public function itemsPerPage(): int;

    public function articlesPerPage(): int;

    public function projectsPerPage(): int;

    public function experienceItemsPerPage(): int;
}
