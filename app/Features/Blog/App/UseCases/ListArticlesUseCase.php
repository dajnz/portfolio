<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\AdminArticleListDto;
use App\Features\Blog\App\DTOs\AdminArticleListQueryDto;
use App\Features\Blog\App\DTOs\ArticleListItemDto;
use App\Features\Blog\App\DTOs\ArticleTagOptionDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * The admin articles list (admin-section-ui.md:387-403): cursor-
 * paginated via the shared app-layer paginator abstraction (the
 * filtered query comes from the list query repository, the pagination
 * facts from the paginator), with the filter bar — title/content
 * search (`?q=`), the separate article-tag / skill-tag AND-intersects
 * (`?article_tags=` / `?skill_tags=` per routes.md:204-205, ADR-0004), the
 * published status (`?published=1|0`) and the publication year
 * (`?year=YYYY`) — plus the trashed view (`?trashed=1`). The raw list
 * rows are converted to the item DTOs (with their tag chips and
 * related rows) here; the tag picker options of the page ride along
 * in the result.
 */
final class ListArticlesUseCase
{
    public function __construct(
        private readonly ArticleListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminListPaginatorInterface $paginator,
    ) {
    }

    public function list(AdminArticleListQueryDto $query): AdminArticleListDto
    {
        $result = $this->paginator->paginate(
            queryFactory: fn (): mixed => $this->repository->adminListQuery(
                $query->search,
                $query->articleTagIds,
                $query->skillTagIds,
                $query->published,
                $query->year,
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
                Config::get('portfolio.items_per_page', 12),
            ),
            countCacheKey: $this->countCacheKey($query),
            requestUrl: $query->url,
        );

        return new AdminArticleListDto(
            rows: $this->rowsWithTagsAndRelated($result->rows),
            pagination: $result->pagination,
            tags: $this->tagOptions(),
            years: $this->repository->yearsOfPublishedArticles(),
        );
    }

    /**
     * The list rows hydrated with their unified tag chips and the
     * related-article counts (the Tags and Related columns of the
     * admin table, admin-section-ui.md:389 — the Related column is a
     * static count, user request 2026-08-13).
     *
     * @param  object[]  $rows
     * @return ArticleListItemDto[]
     */
    private function rowsWithTagsAndRelated(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $tags = $this->repository->articleTagsFor($ids);
        $skills = $this->repository->skillTagsFor($ids);
        $relatedCounts = $this->repository->relatedCountFor($ids);

        return array_map(
            static fn (object $row): ArticleListItemDto => ArticleListItemDto::fromRow(
                $row,
                tags: self::tagChips($tags[(int) $row->id] ?? [], $skills[(int) $row->id] ?? []),
                relatedCount: $relatedCounts[(int) $row->id] ?? 0,
            ),
            $rows,
        );
    }

    /**
     * The unified tag/skill chip set of an article row (the `type`
     * discriminator, admin-section-components.md:623).
     *
     * @param  object[]  $tags  Rows `{id, slug, name}`.
     * @param  TechSkillTag[]  $skills
     * @return ArticleTagOptionDto[]
     */
    private static function tagChips(array $tags, array $skills): array
    {
        $chips = array_map(
            static fn (object $tag): ArticleTagOptionDto => ArticleTagOptionDto::forArticleTag(
                new TagOptionDto(id: (int) $tag->id, slug: $tag->slug, name: $tag->name),
            ),
            $tags,
        );

        foreach ($skills as $skill) {
            $chips[] = ArticleTagOptionDto::forSkillTag($skill);
        }

        return $chips;
    }

    /**
     * The tag picker options of the list page (all article tags).
     *
     * @return TagOptionDto[]
     */
    private function tagOptions(): array
    {
        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            $this->repository->findAllTags(App::getLocale()),
        );
    }

    /**
     * The 30 s count cache is per filter combination (ADR-0010) — every
     * filter changes the counted set.
     */
    private function countCacheKey(AdminArticleListQueryDto $query): string
    {
        return 'articles:'.($query->trashed ? 'trashed' : 'live')
            .':q='.md5($query->search)
            .':at='.md5(implode(',', $query->articleTagIds))
            .':st='.md5(implode(',', $query->skillTagIds))
            .':p='.($query->published === null ? 'x' : ($query->published ? '1' : '0'))
            .':y='.($query->year ?? 'x');
    }
}
