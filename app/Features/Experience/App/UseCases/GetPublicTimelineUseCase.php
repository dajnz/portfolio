<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Config;

/**
 * The public experience timeline: published, non-trashed items ordered
 * `started_at DESC, id DESC`, infinite-scrolled via the opaque composite
 * cursor (`{id, v: started_at}` — the controller encodes/decodes the
 * base64url form via the shared
 * {@see \App\Features\Shared\Infra\Persistence\PublicCursorCodec}).
 *
 * Every request returns a superset of earlier positions: the initial
 * batch unconditionally, then all batches up to and including the one
 * containing the cursor item, then one batch beyond it. The extra batch
 * is what lets a "Load More" request advance — a response ending at the
 * sent cursor would pin the client forever — and `next_cursor` (the last
 * item of the final batch) doubles as the exhaustion signal: `null`
 * whenever that batch is incomplete (fewer than `perPage` rows — no
 * `COUNT(*)` pre-query).
 *
 * Project references (locale-resolved titles/slugs + the project's
 * tech tags) come from query-builder reads inside the Experience Infra;
 * the per-card tag union is assembled by the controller from the item's
 * own {@see TimelineItem::toTechTags()} and the project's tags. The
 * page-level flags ride along: `has_projects` (the header nav condition)
 * and the about-info name for the SEO title — both query-builder reads
 * inside the same Infra repository, so the controller stays query-free.
 *
 * @return array{
 *     items: TimelineItem[],
 *     project_refs: array<int, array{name: string, slug: string, tech_tags: array<int, \App\Features\Shared\Domain\Entities\TechSkillTag>}>,
 *     next_cursor: array{id: int, started_at: string}|null,
 *     has_projects: bool,
 *     about_name: string|null,
 * }
 */
final class GetPublicTimelineUseCase
{
    public function __construct(
        private readonly TimelineItemListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function get(
        ?int $cursorId,
        ?string $cursorStartedAt,
        string $locale,
    ): array {
        $reconstructed = $this->reconstructView($cursorId, $cursorStartedAt);

        return [
            'items' => $reconstructed['items'],
            'project_refs' => $this->repository->projectReadModels(
                $this->projectIdsOf($reconstructed['items']),
                $locale,
            ),
            'next_cursor' => $this->nextCursorFor(
                $reconstructed['items'],
                $reconstructed['last_batch_full'],
            ),
            'has_projects' => $this->repository->hasPublishedProjects(),
            'about_name' => $this->repository->aboutNameFor($locale),
        ];
    }

    /**
     * The reconstruction loop of the superset response: the initial
     * batch, then the batches up to and including the cursor's batch,
     * then one batch beyond the cursor (the "Load More" progress
     * signal). `last_batch_full` marks whether the FINAL batch was
     * complete — the exhaustion signal for `next_cursor`.
     *
     * @return array{items: TimelineItem[], last_batch_full: bool}
     */
    private function reconstructView(?int $cursorId, ?string $cursorStartedAt): array
    {
        $perPage = $this->perPage();

        $items = $this->repository->findPublicPage($perPage, null, null);
        $lastBatchFull = count($items) >= $perPage;

        if ($cursorId !== null) {
            ['items' => $items, 'last_batch_full' => $lastBatchFull] = $this->appendBatchesThroughCursor(
                $items,
                $cursorId,
                (string) $cursorStartedAt,
                $perPage,
            );

            if ($lastBatchFull) {
                ['items' => $items, 'last_batch_full' => $lastBatchFull] = $this->appendBatchAfterCursor(
                    $items,
                    $cursorId,
                    $cursorStartedAt,
                    $perPage,
                );
            }
        }

        return ['items' => $items, 'last_batch_full' => $lastBatchFull];
    }

    /**
     * Appends batches (keyed after the previously appended batch's last
     * item) while that last item sorts before the cursor — i.e. until
     * the batch containing the cursor item is included ("up to and
     * including the cursor position").
     *
     * @param  TimelineItem[]  $items
     * @return array{items: TimelineItem[], last_batch_full: bool}
     */
    private function appendBatchesThroughCursor(
        array $items,
        int $cursorId,
        string $cursorStartedAt,
        int $perPage,
    ): array {
        $lastBatchFull = count($items) >= $perPage;
        $last = $items[count($items) - 1] ?? null;

        while ($last !== null && $this->positionBefore($last, $cursorId, $cursorStartedAt)) {
            $batch = $this->repository->findPublicPage(
                $perPage,
                $last->toId(),
                $last->toPeriod()->toStartedAt()->format('Y-m-d'),
            );

            if ($batch === []) {
                return ['items' => $items, 'last_batch_full' => false];
            }

            $items = array_merge($items, $batch);
            $last = $batch[count($batch) - 1];
            $lastBatchFull = count($batch) >= $perPage;

            if (! $lastBatchFull) {
                return ['items' => $items, 'last_batch_full' => false];
            }
        }

        return ['items' => $items, 'last_batch_full' => $lastBatchFull];
    }

    /**
     * Appends one batch keyed after the cursor itself. Keying after
     * the CURSOR (not after the last appended item) is deliberate: the
     * client already holds the batches up to the cursor, so the extra
     * batch must start exactly where they left off.
     *
     * @param  TimelineItem[]  $items
     * @return array{items: TimelineItem[], last_batch_full: bool}
     */
    private function appendBatchAfterCursor(
        array $items,
        int $cursorId,
        ?string $cursorStartedAt,
        int $perPage,
    ): array {
        $extra = $this->repository->findPublicPage($perPage, $cursorId, $cursorStartedAt);

        if ($extra === []) {
            return ['items' => $items, 'last_batch_full' => false];
        }

        return [
            'items' => array_merge($items, $extra),
            'last_batch_full' => count($extra) >= $perPage,
        ];
    }

    private function perPage(): int
    {
        return (int) $this->settings->value(
            SettingName::ExperienceItemsPerPage,
            Config::get('portfolio.experience_items_per_page', 15),
        );
    }

    /**
     * Whether the item sorts strictly before the cursor position in the
     * `started_at DESC, id DESC` order (later date, or equal date with
     * a higher id — the deterministic tiebreaker).
     */
    private function positionBefore(TimelineItem $item, int $cursorId, string $cursorStartedAt): bool
    {
        $startedAt = $item->toPeriod()->toStartedAt()->format('Y-m-d');

        return $startedAt > $cursorStartedAt
            || ($startedAt === $cursorStartedAt && $item->toId() > $cursorId);
    }

    /**
     * @param  TimelineItem[]  $items
     * @return int[]
     */
    private function projectIdsOf(array $items): array
    {
        $projectIds = [];
        foreach ($items as $item) {
            $projectId = $item->toRelatedProject()?->toOwnProjectId();

            if ($projectId !== null) {
                $projectIds[$projectId] = $projectId;
            }
        }

        return array_values($projectIds);
    }

    /**
     * @param  TimelineItem[]  $items
     * @return array{id: int, started_at: string}|null
     */
    private function nextCursorFor(array $items, bool $lastBatchFull): ?array
    {
        $last = $items[count($items) - 1] ?? null;

        if ($last === null || ! $lastBatchFull) {
            return null;
        }

        return [
            'id' => $last->toId(),
            'started_at' => $last->toPeriod()->toStartedAt()->format('Y-m-d'),
        ];
    }
}
