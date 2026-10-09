<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\ProjectSearchResultDto;
use App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface;
use App\Features\Shared\App\Services\PaginationBuilder;

/**
 * The JSON autocomplete variant of `GET /admin/projects`: ILIKE title
 * search over all locales, unpublished included, length-aware
 * pagination.
 */
final class AdminProjectSearchUseCase
{
    public function __construct(
        private readonly ProjectListQueryRepositoryInterface $repository,
        private readonly PaginationBuilder $pagination,
    ) {
    }

    public function search(string $search, int $page, int $perPage): ProjectSearchResultDto
    {
        $result = $this->repository->findForAdminAutocomplete($search, $page, $perPage);

        return new ProjectSearchResultDto(
            rows: array_map(static fn (object $row): ProjectListItemDto => ProjectListItemDto::fromRow($row), $result['rows']),
            pagination: $this->pagination->lengthAware($page, $perPage, $result['total'], count($result['rows'])),
        );
    }
}
