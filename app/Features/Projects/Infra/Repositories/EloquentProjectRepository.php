<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Repositories;

use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetail;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetailTranslation;
use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Projects\Domain\Exceptions\ProjectTagAlreadyAttachedException;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Infra\Mappers\ProjectEngineeringDetailMapper;
use App\Features\Projects\Infra\Mappers\ProjectMapper;
use App\Features\Projects\Infra\Mappers\ProjectScreenshotMapper;
use App\Features\Projects\Infra\Mappers\ProjectTranslationMapper;
use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Projects\Infra\Models\ProjectTechTagModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\Shared\Infra\Services\SitemapService;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed repository for the Projects feature: entity-based
 * CRUD over `projects` plus the admin quick actions. The boundary is
 * persistence ↔ domain — entities and primitives only, no DTOs, no
 * file/asset operations (the RustFS orchestration is the use cases'
 * concern via the shared asset cleaner).
 *
 * Writes go through a single transaction per aggregate: the base row,
 * the translation sync (batched delete + insert with the locale
 * `search_vector`s rebuilt in one statement — the per-row `saved`
 * events are replaced by the equivalent batch), the screenshot
 * reconcile (upsert by id, batch insert, deleted absent rows), the
 * detail-item reconcile (translations batched) and the tech-tag pivot
 * sync (batch insert + the persisted `tech_tags_count` adjusted
 * explicitly — the same explicit-decrement pattern as `detachTag()`).
 * No query runs in a loop.
 *
 * The translation sync preserves rows of locales disabled in the
 * settings; every ACTIVE-locale row absent from the entity is deleted.
 *
 * The bulk lifecycle ops (delete/restore/force-delete/publication)
 * run as batched queries with a single sitemap invalidation — the
 * per-instance `saved`/`deleted` events fire on the single-item paths
 * only.
 */
final class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function __construct(
        private readonly ProjectMapper $mapper,
        private readonly ProjectTranslationMapper $translationMapper,
        private readonly ProjectScreenshotMapper $screenshotMapper,
        private readonly ProjectEngineeringDetailMapper $detailItemMapper,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function findById(int $id): ?Project
    {
        $model = $this->scopedQuery()
            ->find($id);

        return $model === null ? null : $this->hydrate($model);
    }

    public function findByIdIncludingTrashed(int $id): ?Project
    {
        $model = $this->scopedQuery(withTrashed: true)
            ->find($id);

        return $model === null ? null : $this->hydrate($model);
    }

    public function findBySlug(string $slug): ?Project
    {
        $model = $this->scopedQuery()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $model === null ? null : $this->hydrate($model);
    }

    public function findBySlugIncludingUnpublished(string $slug): ?Project
    {
        $model = $this->scopedQuery()
            ->where('slug', $slug)
            ->first();

        return $model === null ? null : $this->hydrate($model);
    }

    public function slugsOfProjects(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $slugs = [];
        foreach (ProjectModel::withTrashed()->whereIn('id', $ids)->pluck('slug', 'id') as $id => $slug) {
            $slugs[(int) $id] = (string) $slug;
        }

        return $slugs;
    }

    public function slugsOccupiedByActive(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return ProjectModel::query()
            ->whereIn('slug', $slugs)
            ->whereNull('deleted_at')
            ->pluck('slug')
            ->all();
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        return ProjectModel::withTrashed()
            ->where('slug', 'like', $base.'%')
            ->when($ignoreId !== null, fn ($query): mixed => $query->where('id', '!=', $ignoreId))
            ->pluck('slug')
            ->all();
    }

    public function currentSlugForOld(string $oldSlug): ?string
    {
        $row = DB::table('old_slugs')
            ->where('old_slug', $oldSlug)
            ->where('entity_type', 'project')
            ->first();

        if ($row === null) {
            return null;
        }

        return ProjectModel::query()
            ->where('id', (int) $row->entity_id)
            ->whereNull('deleted_at')
            ->value('slug');
    }

    public function assetPathsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $paths = [];

        foreach (ProjectModel::withTrashed()->whereIn('id', $ids)->pluck('preview_image', 'id') as $id => $path) {
            if (is_string($path) && $path !== '') {
                $paths[(int) $id][] = $path;
            }
        }

        foreach (ProjectScreenshotModel::query()
            ->whereIn('project_id', $ids)
            ->select(['project_id', 'image_path'])
            ->get() as $screenshot) {
            $paths[(int) $screenshot->project_id][] = $screenshot->image_path;
        }

        return $paths;
    }

    public function save(Project $project): int
    {
        return DB::transaction(function () use ($project): int {
            $existing = $this->existingRow($project);
            $model = ProjectModel::query()->updateOrCreate(
                ['id' => $project->toId()],
                $this->rowData($project),
            );

            $projectId = (int) $model->id;

            if ($existing !== null && $existing->slug !== $project->toSlug()) {
                $this->recordOldSlug($existing->slug, $projectId);
            }

            $this->syncTranslations($projectId, $project);
            $this->syncScreenshots($projectId, $project);
            $this->syncDetailItems($projectId, $project);
            $this->syncTechTags($projectId, $project);

            // The batched translation sync bypasses the per-row `saved`
            // events, so the sitemap invalidation is applied once here:
            // translation changes are reflected in lastmod.
            app(SitemapService::class)->invalidate();

            return $projectId;
        });
    }

    public function delete(int $id): void
    {
        // Instance delete — the model's `deleted` event (sitemap
        // invalidation) fires only for instance-based deletes.
        ProjectModel::query()->find($id)?->delete();
    }

    public function deleteMany(array $ids): void
    {
        ProjectModel::query()->whereIn('id', $ids)->delete();

        app(SitemapService::class)->invalidate();
    }

    public function restore(int $id): void
    {
        $model = ProjectModel::withTrashed()->find($id);

        if ($model !== null) {
            // Instance restore — `restore()` saves the model, firing
            // the `saved` event (sitemap invalidation).
            $model->restore();
        }
    }

    public function restoreMany(array $ids): void
    {
        ProjectModel::withTrashed()->whereIn('id', $ids)->restore();

        app(SitemapService::class)->invalidate();
    }

    public function forceDelete(int $id): void
    {
        $model = ProjectModel::withTrashed()->find($id);

        if ($model !== null) {
            // An `old_slugs` record preserves the slug history; the
            // RustFS file deletion is orchestrated by the use case via
            // the asset cleaner.
            $this->recordOldSlug($model->slug, (int) $model->id);
            $model->forceDelete();
        }
    }

    public function forceDeleteMany(array $ids): void
    {
        $slugRows = $this->oldSlugRowsOf($ids);

        if ($slugRows !== []) {
            DB::table('old_slugs')->upsert(
                $slugRows,
                ['old_slug', 'entity_type'],
                ['entity_id'],
            );
        }

        ProjectModel::withTrashed()->whereIn('id', $ids)->forceDelete();

        app(SitemapService::class)->invalidate();
    }

    public function setPublicationMany(array $ids, bool $published): void
    {
        if ($ids === []) {
            return;
        }

        $query = ProjectModel::query()->whereIn('id', $ids);

        if ($published) {
            // `published_at` is auto-set on the FIRST publish only and
            // immutable thereafter — the coalesce keeps an already-set
            // value untouched.
            $query->update([
                'is_published' => true,
                'published_at' => DB::raw('coalesce(published_at, now())'),
            ]);
        } else {
            $query->update(['is_published' => false]);
        }

        app(SitemapService::class)->invalidate();
    }

    public function setFeatured(int $id, bool $featured): void
    {
        DB::transaction(function () use ($id, $featured): void {
            $model = $this->scopedQuery()->find($id);

            if ($model === null) {
                return;
            }

            $model->update([
                'is_featured' => $featured,
                // The max+1 default applies only when featuring and no
                // order is set yet; unfeaturing preserves the value.
                'featured_sort_order' => $featured && $model->featured_sort_order === null
                    ? (int) ProjectModel::query()
                        ->where('is_featured', true)
                        ->max('featured_sort_order') + 1
                    : $model->featured_sort_order,
            ]);
        });
    }

    public function reorderFeatured(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            ProjectModel::query()
                ->whereIn('id', $ids)
                ->update(['featured_sort_order' => $this->featuredOrderCase($ids)]);
        });
    }

    public function setTechWriteup(int $id, ?int $articleId): void
    {
        $this->scopedQuery()->find($id)?->update([
            'tech_writeup_article_id' => $articleId,
        ]);
    }

    public function attachTag(int $projectId, int $skillId): void
    {
        $exists = ProjectTechTagModel::query()
            ->where('project_id', $projectId)
            ->where('skill_id', $skillId)
            ->exists();

        if ($exists) {
            throw ProjectTagAlreadyAttachedException::forTag($projectId, $skillId);
        }

        // Instance create — the `saved` event increments the count.
        ProjectTechTagModel::query()->create([
            'project_id' => $projectId,
            'skill_id' => $skillId,
        ]);
    }

    public function detachTag(int $projectId, int $skillId): void
    {
        // Instance deletes are impossible on the composite-PK pivot
        // model (Laravel's array-key save queries crash) — the delete
        // and its `tech_tags_count` decrement are explicit.
        $deleted = ProjectTechTagModel::query()
            ->where('project_id', $projectId)
            ->where('skill_id', $skillId)
            ->delete();

        if ($deleted > 0) {
            DB::table('projects')->where('id', $projectId)->decrement('tech_tags_count');
        }
    }

    /**
     * The full query used by every private/read path — eager-loads the
     * aggregate parts (detail items with their translations).
     *
     * @return \Illuminate\Database\Eloquent\Builder<ProjectModel>
     */
    private function scopedQuery(bool $withTrashed = false): \Illuminate\Database\Eloquent\Builder
    {
        $query = ProjectModel::query()
            ->with(['translations', 'screenshots', 'detailItems.translations']);

        return $withTrashed ? $query->withTrashed() : $query;
    }

    private function hydrate(ProjectModel $model): Project
    {
        $tags = $this->techTagsFor([$model->id])[(int) $model->id] ?? [];

        return $this->mapper->toEntity($model, $tags);
    }

    /**
     * The row being replaced on update — needed for the old-slug
     * record.
     */
    private function existingRow(Project $project): ?ProjectModel
    {
        if ($project->toId() === 0) {
            return null;
        }

        return ProjectModel::query()->withTrashed()->find($project->toId());
    }

    /**
     * The base-row data; `published_at` is auto-set on the FIRST
     * publish only and immutable thereafter — the form-save path
     * applies the same rule as the publish quick action.
     *
     * @return array<string, mixed>
     */
    private function rowData(Project $project): array
    {
        $row = $this->mapper->toRow($project);

        if ($project->toIsPublished() && $project->toPublishedAt() === null) {
            $row['published_at'] = now()->format('Y-m-d H:i:s');
        }

        return $row;
    }

    /**
     * Reconciles the project's translation rows from the entity's
     * locale projections: the rows of the entity's locales are replaced
     * wholesale and ACTIVE-locale rows absent from the set are deleted
     * (rows of locales disabled in the settings are preserved
     * untouched) in ONE delete; the insert is ONE batch; the
     * `search_vector`s are rebuilt in ONE statement (the equivalent of
     * the per-row `saved` event of the model).
     */
    private function syncTranslations(int $projectId, Project $project): void
    {
        $locales = array_values(array_unique(array_merge(
            $this->locales->activeLocales(),
            $project->toTranslationLocales(),
        )));

        ProjectTranslationModel::query()
            ->where('project_id', $projectId)
            ->whereIn('locale', $locales)
            ->delete();

        $rows = [];
        foreach ($project->toTranslations() as $translation) {
            $rows[] = $this->translationMapper->toRow($projectId, $translation);
        }

        if ($rows !== []) {
            DB::table('project_translations')->insert($rows);
        }

        $this->rebuildSearchVectors($projectId);
    }

    /**
     * Rebuilds the `search_vector` of the project's translation rows
     * per the locale dictionary — the batch equivalent of the
     * `ProjectTranslationModel` `saved` event.
     */
    private function rebuildSearchVectors(int $projectId): void
    {
        $dictionaries = config('portfolio.fts.locales', []);

        $sql = 'UPDATE project_translations SET search_vector = to_tsvector((CASE locale';
        $bindings = [];
        foreach ((array) $dictionaries as $locale => $dictionary) {
            $sql .= ' WHEN ? THEN ?';
            $bindings[] = (string) $locale;
            $bindings[] = (string) $dictionary;
        }
        $sql .= " ELSE 'english' END)::regconfig,"
            ." title || ' ' || brief_description || ' ' || coalesce(full_description, ''))"
            .' WHERE project_id = ?';
        $bindings[] = $projectId;

        DB::statement($sql, $bindings);
    }

    /**
     * The screenshot reconcile: rows with an existing id are upserted,
     * new entries are inserted in one batch, and DB rows absent from
     * the submitted set are deleted — all in batched queries. The
     * obsolete RustFS files are NOT this repository's concern (the use
     * case computes and deletes them).
     */
    private function syncScreenshots(int $projectId, Project $project): void
    {
        // The pre-existing id set, captured BEFORE the upserts — the
        // cleanup must only touch rows that existed before this sync
        // (new rows carry id 0 and receive fresh ids; comparing the
        // submitted ids against the DB would delete them).
        $preExistingIds = $this->screenshotIdsOf($projectId);
        $submitted = $project->toScreenshots();

        ['upsert_rows' => $upsertRows, 'insert_rows' => $insertRows] = $this->screenshotRows($projectId, $submitted);

        if ($upsertRows !== []) {
            DB::table('project_screenshots')->upsert(
                $upsertRows,
                ['id'],
                ['project_id', 'image_path', 'alt_text', 'title', 'description', 'sort_order', 'updated_at'],
            );
        }

        if ($insertRows !== []) {
            DB::table('project_screenshots')->insert($insertRows);
        }

        $removedIds = array_values(array_diff(
            $preExistingIds,
            array_filter(array_map(static fn ($screenshot): int => $screenshot->toId(), $submitted)),
        ));

        if ($removedIds !== []) {
            ProjectScreenshotModel::query()->whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * The upsert/insert rows of the submitted screenshots — the
     * pre-existing ids upsert, the fresh ones insert.
     *
     * @param  ProjectScreenshot[]  $submitted
     * @return array{upsert_rows: array<int, array<string, mixed>>, insert_rows: array<int, array<string, mixed>>}
     */
    private function screenshotRows(int $projectId, array $submitted): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $upsertRows = [];
        $insertRows = [];

        foreach ($submitted as $screenshot) {
            $row = $this->screenshotMapper->toRow($projectId, $screenshot);
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            // The query-builder writes bypass the model's jsonb casts —
            // the per-locale maps are encoded explicitly.
            $row['alt_text'] = $this->jsonOrNull($row['alt_text']);
            $row['title'] = $this->jsonOrNull($row['title']);
            $row['description'] = $this->jsonOrNull($row['description']);

            if ($screenshot->toId() > 0) {
                $row['id'] = $screenshot->toId();
                $upsertRows[] = $row;
            } else {
                $insertRows[] = $row;
            }
        }

        return ['upsert_rows' => $upsertRows, 'insert_rows' => $insertRows];
    }

    /**
     * @return int[]
     */
    private function screenshotIdsOf(int $projectId): array
    {
        return ProjectScreenshotModel::query()
            ->where('project_id', $projectId)
            ->pluck('id')
            ->all();
    }

    /**
     * The detail-item reconcile: rows with an existing id are upserted,
     * new entries are inserted (their fresh ids drive the translation
     * rows), the per-locale translation rows are replaced in two
     * batched queries, and DB rows absent from the submitted set are
     * deleted (translations cascade via the FK).
     */
    private function syncDetailItems(int $projectId, Project $project): void
    {
        $preExistingIds = $this->detailItemIdsOf($projectId);
        $submitted = $project->toEngineeringDetails();

        $dbIdByItem = $this->upsertDetailItems($projectId, $submitted);
        $this->replaceDetailItemTranslations($submitted, $dbIdByItem);

        $removedIds = array_values(array_diff(
            $preExistingIds,
            array_filter(array_map(static fn ($item): int => $item->toId(), $submitted)),
        ));

        if ($removedIds !== []) {
            ProjectDetailItemModel::query()->whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * @param  ProjectEngineeringDetail[]  $items
     * @return array<int, int>  Index => persisted detail-item id.
     */
    private function upsertDetailItems(int $projectId, array $items): array
    {
        $now = now()->format('Y-m-d H:i:s');
        $upsertRows = [];
        $dbIdByItem = [];

        foreach ($items as $index => $item) {
            $row = $this->detailItemMapper->toRow($projectId, $item);
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            if ($item->toId() > 0) {
                $row['id'] = $item->toId();
                $upsertRows[] = $row;
                $dbIdByItem[$index] = $item->toId();
            } else {
                $dbIdByItem[$index] = (int) DB::table('project_detail_items')->insertGetId($row);
            }
        }

        if ($upsertRows !== []) {
            DB::table('project_detail_items')->upsert(
                $upsertRows,
                ['id'],
                ['project_id', 'icon', 'sort_order', 'updated_at'],
            );
        }

        return $dbIdByItem;
    }

    /**
     * Replaces the translation rows of the submitted detail items in
     * two batched queries (delete + insert).
     *
     * @param  ProjectEngineeringDetail[]  $items
     * @param  array<int, int>  $dbIdByItem  Index => persisted detail-item id.
     */
    private function replaceDetailItemTranslations(array $items, array $dbIdByItem): void
    {
        $dbIds = [];
        $rows = [];
        foreach ($items as $index => $item) {
            $dbId = $dbIdByItem[$index];
            $dbIds[] = $dbId;

            foreach ($item->toTranslations() as $translation) {
                $rows[] = $this->detailItemTranslationRow($dbId, $translation);
            }
        }

        if ($dbIds !== []) {
            // `where($col, $array)` does NOT expand to whereIn in Laravel
            // 13 — it binds the array as a single value
            // (`detail_item_id = ?`) and matches nothing, so the replace
            // silently kept the old rows and the insert below hit the
            // (detail_item_id, locale) PK (regression test:
            // "reconciles reordered engineering details on update").
            DB::table('project_detail_item_translations')
                ->whereIn('detail_item_id', $dbIds)
                ->delete();
        }

        if ($rows !== []) {
            DB::table('project_detail_item_translations')->insert($rows);
        }
    }

    /**
     * @return int[]
     */
    private function detailItemIdsOf(int $projectId): array
    {
        return ProjectDetailItemModel::query()
            ->where('project_id', $projectId)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array{detail_item_id: int, locale: string, title: string, description: string}
     */
    private function detailItemTranslationRow(int $detailItemId, ProjectEngineeringDetailTranslation $translation): array
    {
        return [
            'detail_item_id' => $detailItemId,
            'locale' => $translation->toLocale(),
            'title' => $translation->toTitle(),
            'description' => $translation->toDescription(),
        ];
    }

    /**
     * The tech-tag pivot sync: rows absent from the entity are deleted
     * (with the explicit `tech_tags_count` decrement) and new rows are
     * inserted in one batch (the count increments explicitly — the
     * same pattern as `detachTag()`, since instance `saved` events
     * cannot drive a batch).
     */
    private function syncTechTags(int $projectId, Project $project): void
    {
        $preExistingIds = ProjectTechTagModel::query()
            ->where('project_id', $projectId)
            ->pluck('skill_id')
            ->all();

        $tagIds = [];
        foreach ($project->toTechTags() as $tag) {
            $tagIds[] = $tag->toId();
        }

        $this->deleteRemovedTags($projectId, $preExistingIds, $tagIds);
        $this->insertNewTags($projectId, $preExistingIds, $tagIds);
    }

    /**
     * @param  int[]  $preExistingIds
     * @param  int[]  $tagIds
     */
    private function deleteRemovedTags(int $projectId, array $preExistingIds, array $tagIds): void
    {
        $removedIds = array_values(array_diff($preExistingIds, $tagIds));
        if ($removedIds === []) {
            return;
        }

        // Pivot rows absent from the entity are deleted — explicitly,
        // with the `tech_tags_count` decrement (instance deletes are
        // impossible on the composite-PK pivot model, see detachTag()).
        $removed = ProjectTechTagModel::query()
            ->where('project_id', $projectId)
            ->whereIn('skill_id', $removedIds)
            ->delete();

        if ($removed > 0) {
            DB::table('projects')->where('id', $projectId)->decrement('tech_tags_count', $removed);
        }
    }

    /**
     * @param  int[]  $preExistingIds
     * @param  int[]  $tagIds
     */
    private function insertNewTags(int $projectId, array $preExistingIds, array $tagIds): void
    {
        $newIds = array_values(array_diff($tagIds, $preExistingIds));
        if ($newIds === []) {
            return;
        }

        DB::table('project_tech_tags')->insert(array_map(
            static fn (int $skillId): array => [
                'project_id' => $projectId,
                'skill_id' => $skillId,
            ],
            $newIds,
        ));

        DB::table('projects')->where('id', $projectId)->increment('tech_tags_count', count($newIds));
    }

    /**
     * The `old_slugs` rows of the given projects — the slug history
     * preservation of the bulk force-delete.
     *
     * @param  int[]  $ids
     * @return array<int, array{old_slug: string, entity_type: string, entity_id: int}>
     */
    private function oldSlugRowsOf(array $ids): array
    {
        $rows = [];
        foreach (ProjectModel::withTrashed()->whereIn('id', $ids)->pluck('slug', 'id') as $id => $slug) {
            $rows[] = [
                'old_slug' => (string) $slug,
                'entity_type' => 'project',
                'entity_id' => (int) $id,
            ];
        }

        return $rows;
    }

    /**
     * The single-statement `featured_sort_order` reorder: each id is
     * written as its zero-indexed position in the submitted array.
     *
     * @param  int[]  $ids
     */
    private function featuredOrderCase(array $ids): \Illuminate\Database\Query\Expression
    {
        $case = 'CASE id';
        foreach (array_values($ids) as $position => $projectId) {
            $case .= ' WHEN '.(int) $projectId.' THEN '.$position;
        }
        $case .= ' ELSE featured_sort_order END';

        return DB::raw($case);
    }

    /**
     * Records a slug change in `old_slugs` — the upsert semantics: a
     * slug reverting to a previously recorded value re-points the
     * existing row instead of violating the `(old_slug, entity_type)`
     * unique constraint.
     */
    private function recordOldSlug(string $oldSlug, int $projectId): void
    {
        DB::table('old_slugs')->upsert(
            [
                'old_slug' => $oldSlug,
                'entity_type' => 'project',
                'entity_id' => $projectId,
            ],
            ['old_slug', 'entity_type'],
            ['entity_id'],
        );
    }

    /**
     * Tech tags of the given projects via the pivot ↔ `skills` join,
     * ordered for display (`skills.sort_order`, then id).
     *
     * @param  int[]  $projectIds
     * @return array<int, TechSkillTag[]>  Keyed by project ID.
     */
    private function techTagsFor(array $projectIds): array
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

    /**
     * The query-builder writes bypass the models' casts — jsonb values
     * are encoded explicitly (null stays null).
     */
    private function jsonOrNull(mixed $value): ?string
    {
        return $value === null
            ? null
            : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
