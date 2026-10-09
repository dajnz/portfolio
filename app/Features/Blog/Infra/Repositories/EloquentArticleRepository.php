<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Exceptions\ArticleSkillAlreadyAttachedException;
use App\Features\Blog\Domain\Exceptions\RelatedArticleAlreadyLinkedException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\ValueObjects\ReadingTime;
use App\Features\Blog\Infra\Mappers\ArticleMapper;
use App\Features\Blog\Infra\Mappers\ArticleTranslationMapper;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;

/**
 * Eloquent-backed repository for the Blog feature: entity-based CRUD
 * over `blog_articles` plus the admin quick actions. The boundary is
 * persistence ↔ domain — entities and primitives only, no DTOs, no
 * file/asset operations (the RustFS orchestration is the use cases'
 * concern via the shared asset cleaner).
 *
 * Writes go through a single transaction per aggregate: the base row,
 * the translation sync (batched delete + insert with the per-locale
 * `reading_time_minutes` computed via the domain {@see ReadingTime}
 * and the `search_vector`s rebuilt in one statement — the per-row
 * `saved` events are replaced by the equivalent batch,
 * database.md:627-645), the `article_tag_pivot` / `article_skill_pivot`
 * syncs (batched inserts + deletes) and the `related_articles`
 * reconcile (the bidirectional pairs of the removed/added ids — the
 * pair rows are deleted/inserted in pairs per database.md:453-463).
 * No query runs in a loop.
 *
 * The translation sync preserves rows of locales disabled in the
 * settings (content-management.md:419); every ACTIVE-locale row absent
 * from the entity is deleted.
 *
 * The bulk lifecycle ops (delete/restore/force-delete/publication)
 * run as batched queries; the `sitemap:xml` invalidation is NOT this
 * repository's concern — the write use cases orchestrate it via the
 * shared invalidator (they probe `publishedIdsOf()`).
 */
final class EloquentArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(
        private readonly ArticleMapper $mapper,
        private readonly ArticleTranslationMapper $translationMapper,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function findById(int $id): ?Article
    {
        $model = $this->scopedQuery()
            ->find($id);

        return $model === null ? null : $this->hydrate($model);
    }

    public function findByIdIncludingTrashed(int $id): ?Article
    {
        $model = $this->scopedQuery(withTrashed: true)
            ->find($id);

        return $model === null ? null : $this->hydrate($model);
    }

    public function findBySlug(string $slug): ?Article
    {
        $model = $this->scopedQuery()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $model === null ? null : $this->hydrate($model);
    }

    public function findBySlugIncludingUnpublished(string $slug): ?Article
    {
        $model = $this->scopedQuery()
            ->where('slug', $slug)
            ->first();

        return $model === null ? null : $this->hydrate($model);
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        // The pattern is bound as a parameter (no SQL injection); the
        // LIKE metacharacters of the base are escaped so a slug
        // containing `%`/`_` cannot widen the prefix match (defensive —
        // slugs are validated kebab-case, content-management.md:436).
        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base).'%';

        return ArticleModel::withTrashed()
            ->where('slug', 'like', $pattern)
            ->when($ignoreId !== null, fn ($query): mixed => $query->where('id', '!=', $ignoreId))
            ->pluck('slug')
            ->all();
    }

    public function slugsOfArticles(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $slugs = [];
        foreach (ArticleModel::withTrashed()->whereIn('id', $ids)->pluck('slug', 'id') as $id => $slug) {
            $slugs[(int) $id] = (string) $slug;
        }

        return $slugs;
    }

    public function slugsOccupiedByActive(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        return ArticleModel::query()
            ->whereIn('slug', $slugs)
            ->whereNull('deleted_at')
            ->pluck('slug')
            ->all();
    }

    public function publishedIdsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ArticleModel::withTrashed()
            ->whereIn('id', $ids)
            ->where('is_published', true)
            ->pluck('id')
            ->map('intval')
            ->all();
    }

    public function currentSlugForOld(string $oldSlug): ?string
    {
        $row = DB::table('old_slugs')
            ->where('old_slug', $oldSlug)
            ->where('entity_type', 'article')
            ->first();

        if ($row === null) {
            return null;
        }

        return ArticleModel::query()
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

        foreach (ArticleModel::withTrashed()->whereIn('id', $ids)->pluck('lead_image', 'id') as $id => $path) {
            if (is_string($path) && $path !== '') {
                $paths[(int) $id][] = $path;
            }
        }

        return $paths;
    }

    public function save(Article $article): void
    {
        DB::transaction(function () use ($article): void {
            $existing = $this->existingRow($article);
            $model = ArticleModel::query()->updateOrCreate(
                ['id' => $article->toId()],
                $this->rowData($article),
            );

            $articleId = (int) $model->id;

            if ($existing !== null && $existing->slug !== $article->toSlug()) {
                $this->recordOldSlug($existing->slug, $articleId);
            }

            $this->syncTranslations($articleId, $article);
            $this->syncArticleTags($articleId, $article->toArticleTagIds());
            $this->syncSkillTags($articleId, $article->toSkillTagIds());
            $this->syncRelatedArticles($articleId, $article->toRelatedArticleIds());
        });
    }

    public function delete(int $id): void
    {
        ArticleModel::query()->find($id)?->delete();
    }

    public function deleteMany(array $ids): void
    {
        ArticleModel::query()->whereIn('id', $ids)->delete();
    }

    public function restore(int $id): void
    {
        ArticleModel::withTrashed()->find($id)?->restore();
    }

    public function restoreMany(array $ids): void
    {
        ArticleModel::withTrashed()->whereIn('id', $ids)->restore();
    }

    public function forceDelete(int $id): void
    {
        $model = ArticleModel::withTrashed()->find($id);

        if ($model !== null) {
            // An `old_slugs` record preserves the slug history
            // (content-management.md:905); the RustFS file deletion is
            // orchestrated by the use case via the asset cleaner.
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

        ArticleModel::withTrashed()->whereIn('id', $ids)->forceDelete();
    }

    public function setPublicationMany(array $ids, bool $published): void
    {
        if ($ids === []) {
            return;
        }

        $query = ArticleModel::query()->whereIn('id', $ids);

        if ($published) {
            // `published_at` is auto-set on the FIRST publish only and
            // immutable thereafter (database.md:341) — the coalesce
            // keeps an already-set value untouched.
            $query->update([
                'is_published' => true,
                'published_at' => DB::raw('coalesce(published_at, now())'),
            ]);
        } else {
            $query->update(['is_published' => false]);
        }
    }

    public function attachRelated(int $articleId, int $relatedArticleId): void
    {
        DB::transaction(function () use ($articleId, $relatedArticleId): void {
            if (DB::table('related_articles')
                ->where('article_id', $articleId)
                ->where('related_article_id', $relatedArticleId)
                ->exists()
            ) {
                throw RelatedArticleAlreadyLinkedException::forPair($articleId, $relatedArticleId);
            }

            DB::table('related_articles')->insert([
                ['article_id' => $articleId, 'related_article_id' => $relatedArticleId],
                ['article_id' => $relatedArticleId, 'related_article_id' => $articleId],
            ]);
        });
    }

    public function detachRelated(int $articleId, int $relatedArticleId): void
    {
        DB::table('related_articles')
            ->where(function ($query) use ($articleId, $relatedArticleId): void {
                $query->where('article_id', $articleId)
                    ->where('related_article_id', $relatedArticleId);
            })
            ->orWhere(function ($query) use ($articleId, $relatedArticleId): void {
                $query->where('article_id', $relatedArticleId)
                    ->where('related_article_id', $articleId);
            })
            ->delete();
    }

    public function attachSkill(int $articleId, int $skillId): void
    {
        $exists = DB::table('article_skill_pivot')
            ->where('article_id', $articleId)
            ->where('skill_id', $skillId)
            ->exists();

        if ($exists) {
            throw ArticleSkillAlreadyAttachedException::forSkill($articleId, $skillId);
        }

        DB::table('article_skill_pivot')->insert([
            'article_id' => $articleId,
            'skill_id' => $skillId,
        ]);
    }

    public function detachSkill(int $articleId, int $skillId): void
    {
        DB::table('article_skill_pivot')
            ->where('article_id', $articleId)
            ->where('skill_id', $skillId)
            ->delete();
    }

    /**
     * The full query used by every private/read path — eager-loads the
     * translations.
     *
     * @return \Illuminate\Database\Eloquent\Builder<ArticleModel>
     */
    private function scopedQuery(bool $withTrashed = false): \Illuminate\Database\Eloquent\Builder
    {
        $query = ArticleModel::query()->with(['translations']);

        return $withTrashed ? $query->withTrashed() : $query;
    }

    private function hydrate(ArticleModel $model): Article
    {
        $id = (int) $model->id;

        return $this->mapper->toEntity(
            $model,
            articleTagIds: $this->pivotIds('article_tag_pivot', 'tag_id', $id),
            skillTagIds: $this->pivotIds('article_skill_pivot', 'skill_id', $id),
            relatedArticleIds: $this->relatedIdsOf($id),
        );
    }

    /**
     * The `related_articles` ids of the article — both directions of
     * the pairs, deduplicated (database.md:453-463).
     *
     * @return int[]
     */
    public function relatedIdsOf(int $articleId): array
    {
        return array_map(
            'intval',
            DB::table('related_articles')
                ->where('article_id', $articleId)
                ->pluck('related_article_id')
                ->merge(
                    DB::table('related_articles')
                        ->where('related_article_id', $articleId)
                        ->pluck('article_id')
                )
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * @return int[]
     */
    private function pivotIds(string $table, string $column, int $articleId): array
    {
        return DB::table($table)
            ->where('article_id', $articleId)
            ->pluck($column)
            ->map('intval')
            ->all();
    }

    /**
     * The row being replaced on update — needed for the old-slug
     * record (ADR-0006).
     */
    private function existingRow(Article $article): ?ArticleModel
    {
        if ($article->toId() === 0) {
            return null;
        }

        return ArticleModel::query()->withTrashed()->find($article->toId());
    }

    /**
     * The base-row data; `published_at` is auto-set on the FIRST
     * publish only and immutable thereafter (database.md:341) — the
     * form-save path applies the same rule as the publish quick
     * action.
     *
     * @return array<string, mixed>
     */
    private function rowData(Article $article): array
    {
        $row = $this->mapper->toRow($article);

        if ($article->toIsPublished() && $article->toPublishedAt() === null) {
            $row['published_at'] = Carbon::now()->format('Y-m-d H:i:s');
        }

        return $row;
    }

    /**
     * Reconciles the article's translation rows from the entity's
     * locale projections: the rows of the entity's locales are replaced
     * wholesale and ACTIVE-locale rows absent from the set are deleted
     * (rows of locales disabled in the settings are preserved untouched
     * — content-management.md:419) in ONE delete; the insert is ONE
     * batch (with the `reading_time_minutes` computed here — the
     * domain formula); the `search_vector`s are rebuilt in ONE
     * statement (the equivalent of the per-row `saved` event of the
     * model).
     */
    private function syncTranslations(int $articleId, Article $article): void
    {
        $locales = array_values(array_unique(array_merge(
            $this->locales->activeLocales(),
            $article->toTranslationLocales(),
        )));

        ArticleTranslationModel::query()
            ->where('article_id', $articleId)
            ->whereIn('locale', $locales)
            ->delete();

        $rows = [];
        foreach ($article->toTranslations() as $translation) {
            $rows[] = $this->translationMapper->toRow($articleId, $translation);
        }

        if ($rows !== []) {
            foreach ($rows as &$row) {
                $row['reading_time_minutes'] = ReadingTime::fromText($row['content'])->toMinutes();
            }
            unset($row);

            DB::table('blog_article_translations')->insert($rows);
        }

        $this->rebuildSearchVectors($articleId);
    }

    /**
     * Rebuilds the `search_vector` of the article's translation rows
     * per the locale dictionary (database.md:627-645) — the batch
     * equivalent of the `ArticleTranslationModel` `saved` event.
     */
    private function rebuildSearchVectors(int $articleId): void
    {
        $dictionaries = Config::get('portfolio.fts.locales', []);

        $sql = 'UPDATE blog_article_translations SET search_vector = to_tsvector((CASE locale';
        $bindings = [];
        foreach ((array) $dictionaries as $locale => $dictionary) {
            $sql .= ' WHEN ? THEN ?';
            $bindings[] = (string) $locale;
            $bindings[] = (string) $dictionary;
        }
        $sql .= " ELSE 'english' END)::regconfig,"
            ." title || ' ' || short_description || ' ' || content)"
            .' WHERE article_id = ?';
        $bindings[] = $articleId;

        DB::statement($sql, $bindings);
    }

    /**
     * The `article_tag_pivot` sync: rows absent from the entity are
     * deleted and new rows are inserted in one batch.
     *
     * @param  int[]  $tagIds
     */
    private function syncArticleTags(int $articleId, array $tagIds): void
    {
        $this->syncPivot('article_tag_pivot', 'tag_id', $articleId, $tagIds);
    }

    /**
     * The `article_skill_pivot` sync — same batched replace.
     *
     * @param  int[]  $skillIds
     */
    private function syncSkillTags(int $articleId, array $skillIds): void
    {
        $this->syncPivot('article_skill_pivot', 'skill_id', $articleId, $skillIds);
    }

    /**
     * @param  int[]  $ids
     */
    private function syncPivot(string $table, string $column, int $articleId, array $ids): void
    {
        $preExistingIds = DB::table($table)
            ->where('article_id', $articleId)
            ->pluck($column)
            ->map('intval')
            ->all();

        $removedIds = array_values(array_diff($preExistingIds, $ids));
        if ($removedIds !== []) {
            DB::table($table)
                ->where('article_id', $articleId)
                ->whereIn($column, $removedIds)
                ->delete();
        }

        $newIds = array_values(array_diff($ids, $preExistingIds));
        if ($newIds !== []) {
            DB::table($table)->insert(array_map(
                static fn (int $id): array => [
                    'article_id' => $articleId,
                    $column => $id,
                ],
                $newIds,
            ));
        }
    }

    /**
     * The `related_articles` reconcile: the pairs of the removed ids
     * are deleted in ONE batched query (both directions) and the pairs
     * of the added ids are inserted in ONE batch (both rows each —
     * database.md:453-463).
     *
     * @param  int[]  $relatedArticleIds
     */
    private function syncRelatedArticles(int $articleId, array $relatedArticleIds): void
    {
        $existingIds = $this->relatedIdsOf($articleId);

        $removedIds = array_values(array_diff($existingIds, $relatedArticleIds));
        if ($removedIds !== []) {
            DB::table('related_articles')
                ->where(function ($query) use ($articleId, $removedIds): void {
                    $query->where('article_id', $articleId)
                        ->whereIn('related_article_id', $removedIds);
                })
                ->orWhere(function ($query) use ($articleId, $removedIds): void {
                    $query->where('related_article_id', $articleId)
                        ->whereIn('article_id', $removedIds);
                })
                ->delete();
        }

        $addedIds = array_values(array_diff($relatedArticleIds, $existingIds));
        if ($addedIds !== []) {
            $rows = [];
            foreach ($addedIds as $addedId) {
                $rows[] = ['article_id' => $articleId, 'related_article_id' => $addedId];
                $rows[] = ['article_id' => $addedId, 'related_article_id' => $articleId];
            }

            DB::table('related_articles')->insert($rows);
        }
    }

    /**
     * The `old_slugs` rows of the given articles — the slug history
     * preservation of the bulk force-delete (content-management.md:
     * 905).
     *
     * @param  int[]  $ids
     * @return array<int, array{old_slug: string, entity_type: string, entity_id: int}>
     */
    private function oldSlugRowsOf(array $ids): array
    {
        $rows = [];
        foreach (ArticleModel::withTrashed()->whereIn('id', $ids)->pluck('slug', 'id') as $id => $slug) {
            $rows[] = [
                'old_slug' => (string) $slug,
                'entity_type' => 'article',
                'entity_id' => (int) $id,
            ];
        }

        return $rows;
    }

    /**
     * Records a slug change in `old_slugs` (ADR-0006) — the upsert
     * semantics of database.md:451: a slug reverting to a previously
     * recorded value re-points the existing row instead of violating
     * the `(old_slug, entity_type)` unique constraint.
     */
    private function recordOldSlug(string $oldSlug, int $articleId): void
    {
        DB::table('old_slugs')->upsert(
            [
                'old_slug' => $oldSlug,
                'entity_type' => 'article',
                'entity_id' => $articleId,
            ],
            ['old_slug', 'entity_type'],
            ['entity_id'],
        );
    }
}
