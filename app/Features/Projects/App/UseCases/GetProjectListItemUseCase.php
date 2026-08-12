<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\Domain\Exceptions\ProjectNotFoundException;
use App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface;

/**
 * The single admin list item after a quick action (feature/unfeature,
 * publish/unpublish, tech writeup, tag attach/detach — B180): the
 * mutations run through their own use cases, this one re-reads the
 * row so the list updates without a reload (content-management.md:162).
 */
final class GetProjectListItemUseCase
{
    public function __construct(
        private readonly ProjectListQueryRepositoryInterface $repository,
    ) {
    }

    public function get(int $id): ProjectListItemDto
    {
        $row = $this->repository->findAdminRow($id);

        if ($row === null) {
            throw ProjectNotFoundException::forId($id);
        }

        return ProjectListItemDto::fromRow($row);
    }
}
