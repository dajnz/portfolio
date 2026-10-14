<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\App\Contracts\TimelineItemListQueryRepositoryInterface;
use App\Features\Experience\App\DTOs\AdminTimelineListDto;
use App\Features\Experience\App\DTOs\AdminTimelineListQueryDto;
use App\Features\Experience\App\DTOs\TimelineItemRowDto;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;

/**
 * The admin experience list: cursor-paginated via the shared app-layer
 * paginator abstraction, sortable by date (`?sort_column=started_at`,
 * forward/reverse — `id` stays the fallback), searchable by content (the
 * filtered query comes from the list query repository) and toggled to
 * the trash via `?trashed=1` (only soft-deleted items).
 */
final class ListTimelineItemsUseCase
{
    public function __construct(
        private readonly TimelineItemListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
        private readonly PaginationDefaultsInterface $paginationDefaults,
    ) {
    }

    public function list(AdminTimelineListQueryDto $query, string $locale): AdminTimelineListDto
    {
        $result = $this->paginator->paginate(
            queryFactory: fn (): mixed => $this->repository->adminListQuery(
                $query->trashed,
                $query->search,
            ),
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
            countCacheKey: $this->countCacheKey($query),
            requestUrl: $query->url,
        );

        return new AdminTimelineListDto(
            rows: array_map(
                static fn (object $row): TimelineItemRowDto => TimelineItemRowDto::fromRow($row, $locale),
                $result->rows,
            ),
            pagination: $result->pagination,
        );
    }

    /**
     * The 30 s count cache is per filter combination — the trashed toggle
     * and the search term both change the counted set.
     */
    private function countCacheKey(AdminTimelineListQueryDto $query): string
    {
        return 'experience:'.($query->trashed ? 'trashed' : 'live').':q='.md5($query->search);
    }
}
