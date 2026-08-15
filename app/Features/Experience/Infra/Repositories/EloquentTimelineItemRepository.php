<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Repositories;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Infra\Mappers\TimelineItemMapper;
use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed repository for the Experience feature: entity-based
 * CRUD over `experience_timeline_items` plus the Infra-level read
 * contracts ({@see TimelineItemListQueryRepositoryInterface}).
 *
 * Writes go through a transaction with the translation sync and the
 * tech-tag pivot sync (A2-style reconciliation by `sync`). The
 * translation rows are derived from the entity's locale projections —
 * the entity is the single source of truth for its localized state —
 * and reconciled against the ACTIVE locales only (rows of locales
 * disabled in the settings are preserved untouched per
 * content-management.md:419). Project reads use query-builder joins —
 * no cross-feature class imports (B11).
 */
final class EloquentTimelineItemRepository implements
    TimelineItemRepositoryInterface,
    TimelineItemListQueryRepositoryInterface
{
    public function __construct(
        private readonly TimelineItemMapper $mapper,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function findById(int $id): ?TimelineItem
    {
        $model = TimelineItemModel::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->find($id);

        return $model === null
            ? null
            : $this->hydrate($model, $this->techTagsFor([$id])[$id] ?? []);
    }

    public function predecessorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem
    {
        $model = TimelineItemModel::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $ignoreId))
            ->where(function (Builder $query) use ($startedAt, $ignoreId): void {
                // (started_at, id) tuple ordering (B175): items sharing
                // the start date chain by id — a NEW item (ignoreId
                // null) follows every same-start item (it will hold the
                // max id); an update excludes itself.
                $query->where('started_at', '<', $startedAt->format('Y-m-d'));

                $query->orWhere(function (Builder $same) use ($startedAt, $ignoreId): void {
                    $same->where('started_at', '=', $startedAt->format('Y-m-d'));

                    if ($ignoreId !== null) {
                        $same->where('id', '<', $ignoreId);
                    }
                });
            })
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : $this->hydrate($model, []);
    }

    public function successorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem
    {
        $model = TimelineItemModel::query()
            ->with('translations')
            ->whereNull('deleted_at')
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $ignoreId))
            ->where(function (Builder $query) use ($startedAt, $ignoreId): void {
                $query->where('started_at', '>', $startedAt->format('Y-m-d'));

                if ($ignoreId !== null) {
                    $query->orWhere(function (Builder $same) use ($startedAt, $ignoreId): void {
                        $same->where('started_at', '=', $startedAt->format('Y-m-d'))
                            ->where('id', '>', $ignoreId);
                    });
                }
            })
            ->orderBy('started_at')
            ->orderBy('id')
            ->first();

        return $model === null ? null : $this->hydrate($model, []);
    }

    public function setEndedAt(int $id, DateTimeImmutable $endedAt): void
    {
        TimelineItemModel::query()
            ->whereKey($id)
            ->whereNull('deleted_at')
            ->first()
            ?->update(['ended_at' => $endedAt->format('Y-m-d')]);
    }

    public function delete(int $id): void
    {
        // Instance delete — the model's `deleted` event (sitemap
        // invalidation, B64) fires only for instance-based deletes.
        TimelineItemModel::query()->find($id)?->delete();
    }

    public function restore(int $id): void
    {
        $model = TimelineItemModel::withTrashed()->find($id);

        if ($model !== null) {
            // Instance restore — `restore()` saves the model, firing
            // the `saved` event (sitemap invalidation, B64).
            $model->restore();
        }
    }

    public function forceDelete(int $id): void
    {
        $model = TimelineItemModel::withTrashed()->find($id);

        if ($model !== null) {
            // Translations and the tech-tag pivot cascade via their FKs;
            // the `deleted` event fires for instance-based force deletes.
            $model->forceDelete();
        }
    }

    public function save(TimelineItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $model = TimelineItemModel::query()->updateOrCreate(
                ['id' => $item->toId()],
                $this->mapper->toRow($item),
            );

            $this->syncTranslations($model->id, $item);
            $model->techTags()->sync(array_map(
                static fn (TechSkillTag $tag): int => $tag->toId(),
                $item->toTechTags(),
            ));
        });
    }

    /**
     * @return Builder<TimelineItem>
     */
    public function listQuery(): Builder
    {
        return TimelineItemModel::query()->with('translations');
    }

    /**
     * @return TimelineItem[]
     */
    public function findPublicPage(int $perPage, ?int $cursorId, ?string $cursorStartedAt): array
    {
        $query = TimelineItemModel::query()
            ->with('translations')
            ->published()
            ->whereNull('deleted_at');

        if ($cursorId !== null) {
            $query->where(function (Builder $keyset) use ($cursorStartedAt, $cursorId): void {
                $keyset->where('started_at', '<', $cursorStartedAt)
                    ->orWhere(function (Builder $sameDate) use ($cursorStartedAt, $cursorId): void {
                        $sameDate->where('started_at', '=', $cursorStartedAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        $models = $query
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        return $this->hydrateAll($models);
    }

    /**
     * @return array<int, array{name: string, slug: string, tech_tags: TechSkillTag[]}>
     */
    public function projectReadModels(array $projectIds, string $locale): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('projects as p')
            ->leftJoin('project_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.project_id', '=', 'p.id')->where('pt.locale', '=', $locale);
            })
            ->leftJoin('project_translations as en', function ($join): void {
                $join->on('en.project_id', '=', 'p.id')->where('en.locale', '=', 'en');
            })
            ->whereIn('p.id', $projectIds)
            ->whereNull('p.deleted_at')
            ->select(['p.id', 'p.slug', DB::raw('coalesce(pt.title, en.title) as title')])
            ->get();

        $tagsByProject = $this->projectTechTags($projectIds);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->id] = [
                'name' => $row->title ?? '',
                'slug' => $row->slug,
                'tech_tags' => $tagsByProject[(int) $row->id] ?? [],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    public function projectsForOptions(string $locale): array
    {
        return DB::table('projects as p')
            ->leftJoin('project_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.project_id', '=', 'p.id')->where('pt.locale', '=', $locale);
            })
            ->leftJoin('project_translations as en', function ($join): void {
                $join->on('en.project_id', '=', 'p.id')->where('en.locale', '=', 'en');
            })
            ->whereNull('p.deleted_at')
            ->select(['p.id', 'p.slug', DB::raw('coalesce(pt.title, en.title) as title')])
            ->orderBy(DB::raw('coalesce(pt.title, en.title)'))
            ->get()
            ->map(fn (mixed $row): array => [
                'id' => (int) $row->id,
                'title' => $row->title ?? '',
                'slug' => $row->slug,
            ])
            ->all();
    }

    public function hasPublishedProjects(): bool
    {
        return DB::table('projects')
            ->where('is_published', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function aboutNameFor(string $locale): ?string
    {
        $name = DB::table('about_info as a')
            ->leftJoin('about_info_translations as at', function ($join) use ($locale): void {
                $join->on('at.about_info_id', '=', 'a.id')->where('at.locale', '=', $locale);
            })
            ->leftJoin('about_info_translations as en', function ($join): void {
                $join->on('en.about_info_id', '=', 'a.id')->where('en.locale', '=', 'en');
            })
            ->where('a.id', 1)
            ->value(DB::raw('coalesce(at.name, en.name)'));

        return is_string($name) ? $name : null;
    }

    /**
     * Reconciles the item's translation rows from its locale
     * projections: the rows of the entity's locales are upserted,
     * ACTIVE-locale rows absent from the set are deleted, and rows of
     * locales disabled in the settings are preserved untouched
     * (content-management.md:419). The projections return the exact
     * values for the entity's own locales — no fallback ambiguity.
     */
    private function syncTranslations(int $itemId, TimelineItem $item): void
    {
        $activeLocales = $this->locales->activeLocales();

        TimelineItemTranslationModel::query()
            ->where('item_id', $itemId)
            ->whereIn('locale', $activeLocales)
            ->whereNotIn('locale', $item->toTranslationLocales())
            ->delete();

        $rows = [];
        foreach ($item->toTranslationLocales() as $locale) {
            $rows[] = [
                'item_id' => $itemId,
                'locale' => $locale,
                'position' => $item->positionFor($locale),
                'company_name' => $item->companyNameFor($locale),
                'company_country' => $item->companyCountryFor($locale),
                'short_description' => $item->shortDescriptionFor($locale),
                // The translation sync is a query-builder upsert, which
                // bypasses the model's array cast — encode explicitly.
                'achievements' => $item->achievementsFor($locale) === []
                    ? null
                    : json_encode($item->achievementsFor($locale), JSON_THROW_ON_ERROR),
            ];
        }

        if ($rows === []) {
            return;
        }

        TimelineItemTranslationModel::query()->upsert(
            $rows,
            ['item_id', 'locale'],
            ['position', 'company_name', 'company_country', 'short_description', 'achievements'],
        );
    }

    /**
     * @param  EloquentCollection<int, TimelineItemModel>  $models
     * @return TimelineItem[]
     */
    private function hydrateAll(EloquentCollection $models): array
    {
        if ($models->isEmpty()) {
            return [];
        }

        $tagsByItem = $this->techTagsFor($models->pluck('id')->all());

        return $models
            ->map(fn (TimelineItemModel $model): TimelineItem => $this->hydrate(
                $model,
                $tagsByItem[(int) $model->id] ?? [],
            ))
            ->all();
    }

    /**
     * @param  int[]  $techTags
     */
    private function hydrate(TimelineItemModel $model, array $techTags): TimelineItem
    {
        return $this->mapper->toEntity($model, $techTags);
    }

    /**
     * Tech tags of the given items via the pivot ↔ `skills` join,
     * ordered for display (`skills.sort_order`, then id —
     * content-management.md:79).
     *
     * @param  int[]  $itemIds
     * @return array<int, TechSkillTag[]>  Keyed by experience item ID.
     */
    private function techTagsFor(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $tagsByItem = [];
        foreach (DB::table('experience_item_tech_tags as eit')
            ->join('skills as s', 's.id', '=', 'eit.skill_id')
            ->whereIn('eit.experience_item_id', $itemIds)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->select(['eit.experience_item_id', 's.id', 's.name', 's.icon'])
            ->get() as $row) {
            $tagsByItem[(int) $row->experience_item_id][] = new TechSkillTag(
                id: (int) $row->id,
                skillName: $row->name,
                icon: $row->icon === null ? null : new SkillIcon($row->icon),
            );
        }

        return $tagsByItem;
    }

    /**
     * Tech tags of the given projects via the `project_tech_tags` ↔
     * `skills` join, ordered for display (`skills.sort_order`, then id).
     *
     * @param  int[]  $projectIds
     * @return array<int, TechSkillTag[]>  Keyed by project ID.
     */
    private function projectTechTags(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $tagsByProject = [];
        foreach (DB::table('project_tech_tags as pt')
            ->join('skills as s', 's.id', '=', 'pt.skill_id')
            ->whereIn('pt.project_id', $projectIds)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->select(['pt.project_id', 's.id', 's.name', 's.icon'])
            ->get() as $row) {
            $tagsByProject[(int) $row->project_id][] = new TechSkillTag(
                id: (int) $row->id,
                skillName: $row->name,
                icon: $row->icon === null ? null : new SkillIcon($row->icon),
            );
        }

        return $tagsByProject;
    }
}
