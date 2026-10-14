<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\App\Contracts\FeedbackListQueryRepositoryInterface;
use App\Features\Portfolio\App\DTOs\AdminFeedbackListDto;
use App\Features\Portfolio\App\DTOs\AdminFeedbackListQueryDto;
use App\Features\Portfolio\App\DTOs\FeedbackListItemDto;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;

/**
 * The admin feedback list: cursor-paginated via the shared app-layer
 * paginator abstraction, newest first (default id desc).
 */
final class ListFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
        private readonly PaginationDefaultsInterface $paginationDefaults,
    ) {
    }

    public function list(AdminFeedbackListQueryDto $query): AdminFeedbackListDto
    {
        $result = $this->paginator->paginate(
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
            countCacheKey: 'feedback',
            requestUrl: $query->url,
        );

        return new AdminFeedbackListDto(
            rows: array_map(
                static fn (object $row): FeedbackListItemDto => FeedbackListItemDto::fromRow($row),
                $result->rows,
            ),
            pagination: $result->pagination,
        );
    }
}
