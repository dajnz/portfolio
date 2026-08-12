<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\PaginatedProjectListDto;
use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\ProjectListDto;
use App\Features\Projects\App\DTOs\ProjectListQuery;
use App\Features\Projects\App\DTOs\ProjectSeoDto;
use App\Features\Projects\App\DTOs\SkillOptionDto;
use App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Services\PaginationBuilder;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public projects page (public-section-ui.md:142-175,
 * content-management.md:125-129): published, non-trashed projects with
 * infinite scroll via the opaque composite cursor, the `?tags=`
 * AND/intersect, the FTS `?q=` search (plainto_tsquery + ts_rank — the
 * search takes over the ordering per database.md:651-660) and the
 * `?sort=date|complexity` strategies (`published_at DESC, id DESC` /
 * `tech_tags_count DESC, id DESC`).
 *
 * Every request returns a superset of earlier positions (seo.md:305):
 * the initial batch unconditionally, then all batches up to and
 * including the one containing the cursor item, then one batch beyond
 * it (public-section-ui.md:338-340). `next_cursor` is null whenever
 * the final batch is incomplete (fewer than `perPage` rows — the
 * exhaustion signal, public-section-ui.md:314).
 *
 * The rows carry the sort value of the batch's last item (the
 * published_at timestamp / tech_tags_count / fts_rank) so the
 * next cursor is encoded from the {@see PublicPageCursor}; the raw
 * list rows and the domain tech tags are converted to the app-layer
 * DTOs here — the controller only prepares its output shapes.
 */
final class GetPublicProjectsUseCase
{
    public function __construct(
        private readonly ProjectListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly PaginationBuilder $pagination,
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
            nextCursor: $this->nextCursorFor($rows, $reconstructed['last_batch_full'], $query->sort, $searching),
            skillTags: array_map(
                static fn (TechSkill $skill): SkillOptionDto => new SkillOptionDto(
                    id: $skill->toId(),
                    name: $skill->toName(),
                    icon: $skill->toIcon(),
                ),
                $this->skills->findAll($query->locale),
            ),
            seo: ProjectSeoDto::forIndex($query->locale, $this->about->findSiteName($query->locale)),
        );
    }

    /**
     * The JSON dual-response variant (routes.md:199-212) — length-aware
     * pagination, FTS search, tag intersect and the date/complexity
     * sort; raw field values without highlights (B16).
     */
    public function jsonPage(ProjectListQuery $query): PaginatedProjectListDto
    {
        $result = $this->repository->findPublicJsonPage(
            locale: $query->locale,
            page: $query->page,
            perPage: $query->perPage,
            sort: $query->sort,
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

        while ($last !== null && $this->positionBefore($last, $cursor, $query->sort, $searching)) {
            $batch = $this->fetchPage(
                $query,
                new PublicPageCursor(
                    id: (int) $last->id,
                    sortValue: $this->sortValueOf($last, $query->sort, $searching),
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
            tagIds: $query->tagIds,
            search: $query->search,
            includeUnpublished: $query->includeUnpublished,
        );
    }

    private function resolvePerPage(): int
    {
        return (int) $this->settings->value(
            SettingName::ProjectsPerPage,
            config('portfolio.projects_per_page', 12),
        );
    }

    /**
     * The batch's last item sort value — the cursor payload for the
     * next request (public-section-ui.md:316-327).
     */
    private function sortValueOf(object $row, string $sort, bool $searching): string
    {
        if ($searching) {
            return (string) $row->fts_rank;
        }

        return $sort === 'complexity'
            ? (string) $row->tech_tags_count
            : (string) $row->published_at;
    }

    /**
     * Whether the row sorts strictly before the cursor position in the
     * active order (a greater sort value, or an equal one with a higher
     * id — the deterministic tiebreaker).
     */
    private function positionBefore(object $row, PublicPageCursor $cursor, string $sort, bool $searching): bool
    {
        $value = $this->sortValueOf($row, $sort, $searching);

        return $value > $cursor->toSortValue()
            || ($value === $cursor->toSortValue() && (int) $row->id > $cursor->toId());
    }

    /**
     * @param  object[]  $rows
     */
    private function nextCursorFor(array $rows, bool $lastBatchFull, string $sort, bool $searching): ?PublicPageCursor
    {
        $last = $rows[count($rows) - 1] ?? null;

        if ($last === null || ! $lastBatchFull) {
            return null;
        }

        return new PublicPageCursor(
            id: (int) $last->id,
            sortValue: $this->sortValueOf($last, $sort, $searching),
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
