<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Blog\Infra\Mappers\ArticleTagMapper;
use App\Features\Blog\Infra\Mappers\ArticleTagTranslationMapper;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed repository for the article tags: entity-based CRUD
 * over `article_tags` (hard deletes — the `article_tag_pivot` rows
 * cascade), the A3 slug-collision probe and the tag↔skill name
 * cross-check (the case-insensitive probe against `skills` — content-
 * management.md:695, B130/B132). The tag translation rows are synced
 * batched (the query-builder writes bypass the model events — none are
 * needed here); ACTIVE-locale rows absent from the entity are deleted
 * (rows of disabled locales preserved — content-management.md:419).
 */
final class EloquentArticleTagRepository implements ArticleTagRepositoryInterface
{
    public function __construct(
        private readonly ArticleTagMapper $mapper,
        private readonly ArticleTagTranslationMapper $translationMapper,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function findById(int $id): ?ArticleTag
    {
        $model = ArticleTagModel::query()
            ->with('translations')
            ->find($id);

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        return ArticleTagModel::query()
            ->where('slug', 'like', $base.'%')
            ->when($ignoreId !== null, fn ($query): mixed => $query->where('id', '!=', $ignoreId))
            ->pluck('slug')
            ->all();
    }

    /**
     * Whether ANY of the given tag names matches an existing skill
     * name (case-insensitive, any locale) — the article-tag side of
     * the tag↔skill cross-check (content-management.md:595,695). One
     * parametrized query for the whole batch (no raw user input, no
     * per-name round trips).
     *
     * @param  string[]  $names
     */
    public function anyTagNameMatchesSkill(array $names, ?int $ignoreId = null): bool
    {
        $names = array_values(array_filter(
            array_map('mb_strtolower', $names),
            static fn (string $name): bool => $name !== '',
        ));

        if ($names === []) {
            return false;
        }

        return DB::table('skills')
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhere(DB::raw('lower(name)'), '=', $name);
                }
            })
            ->when($ignoreId !== null, fn ($query): mixed => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    public function save(ArticleTag $tag): int
    {
        return DB::transaction(function () use ($tag): int {
            $model = ArticleTagModel::query()->updateOrCreate(
                ['id' => $tag->toId()],
                $this->mapper->toRow($tag),
            );

            $tagId = (int) $model->id;

            $locales = array_values(array_unique(array_merge(
                $this->locales->activeLocales(),
                $tag->toTranslationLocales(),
            )));

            ArticleTagTranslationModel::query()
                ->where('article_tag_id', $tagId)
                ->whereIn('locale', $locales)
                ->delete();

            $rows = [];
            foreach ($tag->toTranslations() as $translation) {
                $rows[] = $this->translationMapper->toRow($tagId, $translation);
            }

            if ($rows !== []) {
                DB::table('article_tag_translations')->insert($rows);
            }

            return $tagId;
        });
    }

    public function delete(int $id): void
    {
        ArticleTagModel::query()->find($id)?->delete();
    }
}
