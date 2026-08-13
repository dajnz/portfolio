<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\AdminTagListDto;
use App\Features\Blog\App\DTOs\AdminTagListQueryDto;
use App\Features\Blog\App\DTOs\TagListItemDto;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;
use Illuminate\Support\Facades\Config;

/**
 * The admin tags list (admin-section-ui.md:315-320): cursor-paginated
 * via the shared app-layer paginator abstraction, with the
 * cross-locale name search (`?q=`) and the `article_tag_pivot` usage
 * count per row (the Article Count column). The raw rows are converted
 * to the row DTOs here.
 */
final class ListTagsUseCase
{
    public function __construct(
        private readonly ArticleListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
    ) {
    }

    public function list(AdminTagListQueryDto $query, string $locale): AdminTagListDto
    {
        $result = $this->paginator->paginate(
            queryFactory: fn (): mixed => $this->repository->tagsListQuery($query->search, $locale),
            query: new PaginationQuery(
                page: $query->page,
                cursorParams: $query->cursorParams,
                sortColumn: $query->sortColumn,
                sortDirection: $query->sortDirection,
            ),
            perPage: (int) $this->settings->value(
                SettingName::ItemsPerPage,
                Config::get('portfolio.items_per_page', 12),
            ),
            countCacheKey: 'tags:q='.md5($query->search),
            requestUrl: $query->url,
        );

        return new AdminTagListDto(
            rows: array_map(
                static fn (object $row): TagListItemDto => TagListItemDto::fromRow($row),
                $result->rows,
            ),
            pagination: $result->pagination,
        );
    }
}
