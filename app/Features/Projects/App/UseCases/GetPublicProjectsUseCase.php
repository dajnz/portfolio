<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\PaginatedProjectListDto;
use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\ProjectListDto;
use App\Features\Projects\App\DTOs\ProjectListQuery;
use App\Features\Projects\App\DTOs\SkillOptionDto;
use App\Features\Projects\App\Services\ProjectSeoFactory;
use App\Features\Projects\App\Contracts\ProjectListQueryRepositoryInterface;
use App\Features\Shared\App\Services\PageSizeResolver;
use App\Features\Shared\App\Services\PaginationBuilder;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public projects page: published, non-trashed projects with
 * infinite scroll via the opaque composite cursor, the `?tags=`
 * AND/intersect, the FTS `?q=` search (plainto_tsquery + ts_rank — the
 * search takes over the ordering) and the `date` sort strategy with the
 * `?direction=asc|desc` variant (`published_at {dir}, id {dir}`).
 *
 * Every request returns a superset of earlier positions: the initial
 * batch unconditionally, then all batches up to and including the one
 * containing the cursor item, then one batch beyond it. `next_cursor`
 * is null whenever the final batch is incomplete (fewer than `perPage`
 * rows — the exhaustion signal).
 *
 * The rows carry the sort value of the batch's last item (the
 * published_at timestamp / fts_rank) so the next cursor is encoded from
 * the {@see PublicPageCursor}; the raw list rows and the domain tech
 * tags are converted to the app-layer DTOs here — the controller only
 * prepares its output shapes.
 */
final class GetPublicProjectsUseCase
{
    public function __construct(
        private readonly ProjectListQueryRepositoryInterface $repository,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly PaginationBuilder $pagination,
        private readonly PageSizeResolver $pageSize,
        private readonly ProjectSeoFactory $seo,
    ) {
    }

    public function get(ProjectListQuery $query): ProjectListDto
    {
        $searching = is_string($query->search) && $query->search !== '';

        $reconstructed = $this->reconstructView($query, $searching);

        /** @var object[] $rows */
        $rows = $reconstructed['rows'];

        $projectIds = array_map(static fn (object $row): int => (int) $row->id, $rows);

        return new ProjectListDto(
            rows: array_map(static fn (object $row): ProjectListItemDto => ProjectListItemDto::fromRow($row), $rows),
            techTags: $this->skillTagDtos($this->repository->techTagsFor($projectIds)),
            screenshots: $this->repository->screenshotsFor($projectIds),
            nextCursor: $this->nextCursorFor($rows, $reconstructed['last_batch_full'], $query, $searching),
            skillTags: array_map(
                static fn (TechSkill $skill): SkillOptionDto => new SkillOptionDto(
                    id: $skill->toId(),
                    name: $skill->toName(),
                    icon: $skill->toIcon(),
                ),
                $this->skills->findAll($query->locale),
            ),
            seo: $this->seo->forIndex($query->locale),
        );
    }

    /**
     * The JSON dual-response variant — length-aware pagination, FTS
     * search, tag intersect and the date sort with the direction
     * variant; raw field values without highlights.
     */
    public function jsonPage(ProjectListQuery $query): PaginatedProjectListDto
    {
        $result = $this->repository->findPublicJsonPage(
            locale: $query->locale,
            page: $query->page,
            perPage: $query->perPage,
            sort: $query->sort,
            sortDirection: $query->sortDirection,
            tagIds: $query->tagIds,
            search: $query->search,
        );

        return new PaginatedProjectListDto(
            rows: array_map(
                static fn (object $row): ProjectListItemDto => ProjectListItemDto::fromRow($row),
                $result['rows'],
            ),
            techTags: $this->skillTagDtos($this->repository->techTagsFor(
                array_map(static fn (object $row): int => (int) $row->id, $result['rows']),
            )),
            pagination: $this->pagination->lengthAware(
                $query->page,
                $query->perPage,
                $result['total'],
                count($result['rows']),
            ),
        );
    }

    /**
     * @return array{rows: array<int, object>, last_batch_full: bool}
     */
    private function reconstructView(ProjectListQuery $query, bool $searching): array
    {
        $perPage = $this->resolvePerPage();

        $items = $this->fetchPage($query, null, $perPage);
        $lastBatchFull = count($items) >= $perPage;

        if ($query->cursor !== null) {
            ['rows' => $items, 'last_batch_full' => $lastBatchFull] = $this->appendBatchesThroughCursor(
                $items,
                $query,
                $searching,
                $query->cursor,
                $perPage,
            );

            if ($lastBatchFull) {
                ['rows' => $items, 'last_batch_full' => $lastBatchFull] = $this->appendBatchAfterCursor(
                    $items,
                    $query,
                    $query->cursor,
                    $perPage,
                );
            }
        }

        return ['rows' => $items, 'last_batch_full' => $lastBatchFull];
    }

    /**
     * Appends batches (keyed after the previously appended batch's last
     * item) while that last item sorts before the cursor — i.e. until
     * the batch containing the cursor item is included.
     *
     * @param  object[]  $rows
     * @return array{rows: array<int, object>, last_batch_full: bool}
     */
    private function appendBatchesThroughCursor(
        array $rows,
        ProjectListQuery $query,
        bool $searching,
        PublicPageCursor $cursor,
        int $perPage,
    ): array {
        $lastBatchFull = count($rows) >= $perPage;
        $last = $rows[count($rows) - 1] ?? null;

        while ($last !== null && $this->positionBefore($last, $cursor, $query, $searching)) {
            $batch = $this->fetchPage(
                $query,
                new PublicPageCursor(
                    id: (int) $last->id,
                    sortValue: $this->sortValueOf($last, $query, $searching),
                ),
                $perPage,
            );

            if ($batch === []) {
                return ['rows' => $rows, 'last_batch_full' => false];
            }

            $rows = array_merge($rows, $batch);
            $last = $batch[count($batch) - 1];
            $lastBatchFull = count($batch) >= $perPage;

            if (! $lastBatchFull) {
                return ['rows' => $rows, 'last_batch_full' => false];
            }
        }

        return ['rows' => $rows, 'last_batch_full' => $lastBatchFull];
    }

    /**
     * Appends one batch keyed after the cursor itself — the client
     * already holds the batches up to the cursor, so the extra batch
     * must start exactly where they left off.
     *
     * @param  object[]  $rows
     * @return array{rows: array<int, object>, last_batch_full: bool}
     */
    private function appendBatchAfterCursor(
        array $rows,
        ProjectListQuery $query,
        PublicPageCursor $cursor,
        int $perPage,
    ): array {
        $extra = $this->fetchPage($query, $cursor, $perPage);

        if ($extra === []) {
            return ['rows' => $rows, 'last_batch_full' => false];
        }

        return [
            'rows' => array_merge($rows, $extra),
            'last_batch_full' => count($extra) >= $perPage,
        ];
    }

    /**
     * @return object[]
     */
    private function fetchPage(ProjectListQuery $query, ?PublicPageCursor $cursor, int $perPage): array
    {
        return $this->repository->findPublicPageRows(
            locale: $query->locale,
            perPage: $perPage,
            cursor: $cursor,
            sort: $query->sort,
            sortDirection: $query->sortDirection,
            tagIds: $query->tagIds,
            search: $query->search,
            includeUnpublished: $query->includeUnpublished,
        );
    }

    private function resolvePerPage(): int
    {
        return $this->pageSize->projectsPerPage();
    }

    /**
     * The batch's last item sort value — the cursor payload for the
     * next request. The FTS rank while searching, the published
     * timestamp otherwise.
     */
    private function sortValueOf(object $row, ProjectListQuery $query, bool $searching): string
    {
        if ($searching) {
            return (string) $row->fts_rank;
        }

        return (string) $row->published_at;
    }

    /**
     * Whether the row sorts strictly before the cursor position in the
     * active order — a greater sort value (or an equal one with a
     * higher id, the deterministic tiebreaker) in `desc` order, the
     * exact mirror in `asc` order.
     */
    private function positionBefore(object $row, PublicPageCursor $cursor, ProjectListQuery $query, bool $searching): bool
    {
        $value = $this->sortValueOf($row, $query, $searching);
        $after = $value > $cursor->toSortValue()
            || ($value === $cursor->toSortValue() && (int) $row->id > $cursor->toId());

        return $query->sortDirection === 'asc' ? ! $after : $after;
    }

    /**
     * @param  object[]  $rows
     */
    private function nextCursorFor(array $rows, bool $lastBatchFull, ProjectListQuery $query, bool $searching): ?PublicPageCursor
    {
        $last = $rows[count($rows) - 1] ?? null;

        if ($last === null || ! $lastBatchFull) {
            return null;
        }

        return new PublicPageCursor(
            id: (int) $last->id,
            sortValue: $this->sortValueOf($last, $query, $searching),
        );
    }

    /**
     * The domain tech tags converted to the app-layer option DTOs.
     *
     * @param  array<int, array<int, TechSkillTag>>  $tagsByProject
     * @return array<int, array<int, SkillOptionDto>>
     */
    private function skillTagDtos(array $tagsByProject): array
    {
        return array_map(
            static fn (array $tags): array => array_map(
                static fn (TechSkillTag $tag): SkillOptionDto => new SkillOptionDto(
                    id: $tag->toId(),
                    name: $tag->toSkillName(),
                    icon: $tag->toIcon(),
                ),
                $tags,
            ),
            $tagsByProject,
        );
    }
}
