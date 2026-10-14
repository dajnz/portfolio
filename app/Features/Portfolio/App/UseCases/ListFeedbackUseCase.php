<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\App\Contracts\FeedbackListQueryRepositoryInterface;
use App\Features\Portfolio\App\DTOs\AdminFeedbackListQueryDto;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\App\DTOs\PaginationResultDto;

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

    public function list(AdminFeedbackListQueryDto $query): PaginationResultDto
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
            countCacheKey: 'feedback',
            requestUrl: $query->url,
        );
    }
}
