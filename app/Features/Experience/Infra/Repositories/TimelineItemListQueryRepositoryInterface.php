<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Repositories;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use Illuminate\Database\Eloquent\Builder;

/**
 * Infra-layer read contracts for the Experience feature (B11: query-
 * builder reads live in the Infra layer — the domain repository
 * interface stays framework-free). Implemented by
 * {@see EloquentTimelineItemRepository}.
 */
interface TimelineItemListQueryRepositoryInterface
{
    /**
     * Base query for the admin cursor-paginated list, with the
     * translations eager-loaded for the list hydration.
     *
     * @return Builder<TimelineItem>
     */
    public function listQuery(): Builder;

    /**
     * One keyset batch of the public timeline: published, non-trashed
     * items after the cursor position in the `started_at DESC, id DESC`
     * order, at most `$perPage` rows — hydrated with translations and
     * the tech-tag rows.
     *
     * @return TimelineItem[]
     */
    public function findPublicPage(int $perPage, ?int $cursorId, ?string $cursorStartedAt): array;

    /**
     * Locale-resolved project references for the public cards: the
     * title (requested locale with `en` fallback), the slug, and the
     * project's tech tags (`project_tech_tags` ↔ `skills` join).
     * Trashed projects are excluded — their links would 404 on the
     * public site.
     *
     * @param  int[]  $projectIds
     * @return array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}>  Keyed by project ID.
     */
    public function projectReadModels(array $projectIds, string $locale): array;

    /**
     * Non-trashed portfolio projects for the form's searchable
     * combobox, locale-resolved titles (`en` fallback), ordered by
     * title.
     *
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    public function projectsForOptions(string $locale): array;
}
