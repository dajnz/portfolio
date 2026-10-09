<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleListItemDto;
use App\Features\Blog\App\DTOs\ArticleSearchResultDto;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;
use App\Features\Shared\App\Services\PaginationBuilder;

/**
 * The JSON autocomplete variant of `GET /admin/blog`: ILIKE title
 * search over all locales, unpublished articles included, length-aware
 * pagination — the raw rows are converted to the item DTOs and the
 * pagination facts are built by the app-layer pagination service here.
 */
final class AdminArticleSearchUseCase
{
    public function __construct(
        private readonly ArticleListQueryRepositoryInterface $repository,
        private readonly PaginationBuilder $pagination,
    ) {
    }

    public function search(string $search, int $page, int $perPage): ArticleSearchResultDto
    {
        $result = $this->repository->findForAdminAutocomplete($search, $page, $perPage);

        return new ArticleSearchResultDto(
            rows: array_map(static fn (object $row): ArticleListItemDto => ArticleListItemDto::fromRow($row), $result['rows']),
            pagination: $this->pagination->lengthAware($page, $perPage, $result['total'], count($result['rows'])),
        );
    }
}
