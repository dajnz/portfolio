<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\AdminProjectListDto;
use App\Features\Projects\App\DTOs\AdminProjectListQuery;
use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\SkillOptionDto;
use App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\Domain\Entities\TechSkillTag;

/**
 * The admin projects list (admin-section-ui.md:327-346): cursor-
 * paginated via the shared app-layer paginator abstraction (the
 * filtered query comes from the list query repository, the pagination
 * facts from the paginator), with the filter bar — title search
 * (`?q=`), the featured toggle (`?featured=1`), the tech-tag
 * AND-intersect (`?tags=1,5`) and the published status
 * (`?published=1|0`) — plus the trashed view (`?trashed=1`). The raw
 * list rows are converted to the item DTOs here.
 */
final class ListProjectsUseCase
{
    public function __construct(
        private readonly ProjectListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
    ) {
    }

    public function list(AdminProjectListQuery $query): AdminProjectListDto
    {
        $result = $this->paginator->paginate(
            queryFactory: fn (): mixed => $this->repository->adminListQuery(
                $query->search,
                $query->featured,
                $query->tags,
                $query->published,
                $query->trashed,
            ),
            query: new PaginationQuery(
                page: $query->page,
                cursorParams: $query->cursorParams,
                sortColumn: $query->sortColumn,
                sortDirection: $query->sortDirection,
            ),
            perPage: (int) $this->settings->value(
                SettingName::ItemsPerPage,
                config('portfolio.items_per_page', 12),
            ),
            countCacheKey: $this->countCacheKey($query),
            requestUrl: $query->url,
        );

        return new AdminProjectListDto(
            rows: $this->rowsWithTags($result->rows),
            pagination: $result->pagination,
            featuredProjects: $query->trashed
                ? []
                : $this->rowsWithTags($this->repository->findFeaturedRows()),
        );
    }

    /**
     * The list rows hydrated with their tech tags (the Tags column of
     * the admin table renders name chips, admin-section-ui.md:343).
     *
     * @param  object[]  $rows
     * @return ProjectListItemDto[]
     */
    private function rowsWithTags(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $tagsByProject = $this->repository->techTagsFor(array_map(
            static fn (object $row): int => (int) $row->id,
            $rows,
        ));

        return array_map(
            static fn (object $row): ProjectListItemDto => ProjectListItemDto::fromRow(
                $row,
                array_map(
                    static fn (TechSkillTag $tag): SkillOptionDto => new SkillOptionDto(
                        id: $tag->toId(),
                        name: $tag->toSkillName(),
                        icon: $tag->toIcon(),
                    ),
                    $tagsByProject[(int) $row->id] ?? [],
                ),
            ),
            $rows,
        );
    }

    /**
     * The 30 s count cache is per filter combination (ADR-0010) — every
     * filter changes the counted set.
     */
    private function countCacheKey(AdminProjectListQuery $query): string
    {
        return 'projects:'.($query->trashed ? 'trashed' : 'live')
            .':q='.md5($query->search)
            .':f='.($query->featured ? '1' : '0')
            .':t='.md5(implode(',', $query->tags))
            .':p='.($query->published === null ? 'x' : ($query->published ? '1' : '0'));
    }
}
