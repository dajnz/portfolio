<?php

declare(strict_types=1);

namespace App\Features\Experience\App\Contracts;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Shared\Domain\Entities\TechSkillTag;

/**
 * App-layer read contract for the Experience feature. The
 * `adminListQuery()` base query is consumed through the shared app-layer
 * paginator, so its framework builder is typed as `mixed` at this
 * boundary; implemented by the Eloquent timeline repository on the Infra
 * layer.
 */
interface TimelineItemListQueryRepositoryInterface
{
    /**
     * The filtered admin list query, with the translations
     * eager-loaded for the list hydration: the trashed view
     * (`?trashed=1`, only soft-deleted items) and the content search
     * over the per-locale text columns (ILIKE — no FTS at admin list
     * scale).
     *
     * @return mixed  The filtered base query (framework builder).
     */
    public function adminListQuery(bool $trashed, string $search): mixed;

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
     * Whether at least one published, non-trashed project exists — the
     * header nav's Projects-link condition.
     */
    public function hasPublishedProjects(): bool;

    /**
     * The single about-info row's name, resolved for the requested locale
     * with the `en` fallback — the SEO title interpolation
     * (`seo.title_experience`). `null` when no about row exists.
     */
    public function aboutNameFor(string $locale): ?string;

    /**
     * Non-trashed portfolio projects for the form's searchable
     * combobox, locale-resolved titles (`en` fallback), ordered by
     * title.
     *
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    public function projectsForOptions(string $locale): array;
}
