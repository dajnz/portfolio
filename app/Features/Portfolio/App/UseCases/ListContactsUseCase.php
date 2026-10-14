<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\App\Contracts\ContactListQueryRepositoryInterface;
use App\Features\Portfolio\App\DTOs\AdminContactListQueryDto;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\App\DTOs\PaginationResultDto;

/**
 * The admin contacts list: cursor-paginated via the shared app-layer
 * paginator abstraction. The reorderable list sorts by `sort_order`
 * (asc) — the frontend requests it via the `?sort_column=`/
 * `?sort_direction=` params; `id` stays the fallback.
 */
final class ListContactsUseCase
{
    public function __construct(
        private readonly ContactListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
        private readonly PaginationDefaultsInterface $paginationDefaults,
    ) {
    }

    public function list(AdminContactListQueryDto $query): PaginationResultDto
    {
        return $this->paginator->paginate(
            queryFactory: fn (): mixed => $this->repository->listQuery(),
            query: new PaginationQuery(
                page: $query->page,
                cursorParams: $query->cursorParams,
                sortColumn: $query->sortColumn,
                sortDirection: $query->sortDirection,
            ),
            perPage: (int) $this->settings->value(
                SettingName::ItemsPerPage,
                $this->paginationDefaults->itemsPerPage(),
            ),
            countCacheKey: 'contacts',
            requestUrl: $query->url,
        );
    }
}
