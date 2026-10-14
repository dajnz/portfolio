<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleCardDto;
use App\Features\Blog\App\DTOs\BlogSeoDto;
use App\Features\Blog\App\DTOs\PaginatedPublicBlogListDto;
use App\Features\Blog\App\DTOs\PublicBlogListDto;
use App\Features\Blog\App\DTOs\PublicBlogListQuery;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\ApplicationUrlInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\App\Services\PaginationBuilder;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public blog index: published, non-trashed articles ordered
 * `published_at DESC, id DESC` with infinite scroll via the opaque
 * composite cursor, the `?article_tags=` / `?skill_tags=`
 * AND-intersects, the `?year=` filter and the FTS `?q=` search
 * (plainto_tsquery + ts_rank — the search takes over the ordering,
 * with the `ts_headline` snippet on the HTML variant).
 *
 * Every request returns a superset of earlier positions: the initial
 * batch unconditionally, then all batches up to and including the one
 * containing the cursor item, then one batch beyond it. `next_cursor`
 * is null whenever the final batch is incomplete (fewer than `perPage`
 * rows — the exhaustion signal).
 *
 * The rows and the resolved tag names are converted to the app-layer
 * DTOs here; the controller only prepares its output shapes.
 */
final class GetPublicBlogIndexUseCase
{
    public function __construct(
        private readonly PublicArticleReadRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly PaginationBuilder $pagination,
        private readonly PaginationDefaultsInterface $paginationDefaults,
        private readonly ApplicationUrlInterface $applicationUrl,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function get(PublicBlogListQuery $query): PublicBlogListDto
    {
        $searching = is_string($query->search) && $query->search !== '';

        $reconstructed = $this->reconstructView($query, $searching);

        /** @var object[] $rows */
        $rows = $reconstructed['rows'];

        return new PublicBlogListDto(
            articles: $this->cards($rows),
            nextCursor: $this->nextCursorFor($rows, $reconstructed['last_batch_full'], $searching),
            tags: $this->tagOptions($this->repository->findAllTags($query->locale)),
            years: $this->repository->yearsOfPublishedArticles(),
            seo: BlogSeoDto::forIndex(
                title: $this->translator->translate(
                    'seo.title_blog_index',
                    ['name' => $this->about->findSiteName($query->locale)],
                ),
                description: $this->translator->translate(
                    'seo.description_blog_index',
                    ['name' => $this->about->findSiteName($query->locale)],
                ),
                baseUrl: $this->applicationUrl->baseUrl(),
            ),
            hasProjects: $this->repository->hasPublishedProjects(),
        );
    }

    /**
     * The JSON dual-response variant — length-aware pagination, the
     * prefix autocomplete search, the tag intersects and the year
     * filter; raw field values without highlights.
     */
    public function jsonPage(PublicBlogListQuery $query): PaginatedPublicBlogListDto
    {
        $result = $this->repository->findPublicJsonPage(
            locale: $query->locale,
            page: $query->page,
            perPage: $query->perPage,
            articleTagIds: $query->articleTagIds,
            skillTagIds: $query->skillTagIds,
            year: $query->year,
            search: $query->search,
        );

        return new PaginatedPublicBlogListDto(
            articles: $this->cards($result['rows']),
            pagination: $this->pagination->lengthAware(
                $query->page,
                $query->perPage,
                $result['total'],
                count($result['rows']),
            ),
        );
    }

    /**
     * @param  object[]  $rows
     * @return ArticleCardDto[]
     */
    private function cards(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $articleIds = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $tagsByArticle = $this->repository->articleTagsFor($articleIds);
        $skillsByArticle = $this->repository->skillTagsFor($articleIds);

        return array_map(function (object $row) use ($tagsByArticle, $skillsByArticle): ArticleCardDto {
            $card = ArticleCardDto::fromRow($row);

            return new ArticleCardDto(
                id: $card->id,
                slug: $card->slug,
                title: $card->title,
                shortDescription: $card->shortDescription,
                publishedAt: $card->publishedAt,
                readingTimeMinutes: $card->readingTimeMinutes,
                leadImage: $card->leadImage,
                snippet: $card->snippet,
                tags: $this->tagOptions($tagsByArticle[$card->id] ?? []),
                skillTags: $skillsByArticle[$card->id] ?? [],
                leadImageAlt: $card->leadImageAlt,
            );
        }, $rows);
    }

    /**
     * @param  object[]  $tagRows  The `{id, slug, name}` rows.
     * @return TagOptionDto[]
     */
    private function tagOptions(array $tagRows): array
    {
        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            $tagRows,
        );
    }

    /**
     * @return array{rows: array<int, object>, last_batch_full: bool}
     */
    private function reconstructView(PublicBlogListQuery $query, bool $searching): array
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
        PublicBlogListQuery $query,
        bool $searching,
        PublicPageCursor $cursor,
        int $perPage,
    ): array {
        $lastBatchFull = count($rows) >= $perPage;
        $last = $rows[count($rows) - 1] ?? null;

        while ($last !== null && $this->positionBefore($last, $cursor, $searching)) {
            $batch = $this->fetchPage(
                $query,
                new PublicPageCursor(
                    id: (int) $last->id,
                    sortValue: $this->sortValueOf($last, $searching),
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
        PublicBlogListQuery $query,
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
    private function fetchPage(PublicBlogListQuery $query, ?PublicPageCursor $cursor, int $perPage): array
    {
        return $this->repository->findPublicPageRows(
            locale: $query->locale,
            perPage: $perPage,
            cursor: $cursor,
            articleTagIds: $query->articleTagIds,
            skillTagIds: $query->skillTagIds,
            year: $query->year,
            search: $query->search,
        );
    }

    private function resolvePerPage(): int
    {
        return (int) $this->settings->value(
            SettingName::ArticlesPerPage,
            $this->paginationDefaults->articlesPerPage(),
        );
    }

    /**
     * The batch's last item sort value — the cursor payload for the
     * next request.
     */
    private function sortValueOf(object $row, bool $searching): string
    {
        return $searching
            ? (string) $row->fts_rank
            : (string) $row->published_at;
    }

    /**
     * Whether the row sorts strictly before the cursor position in the
     * active order (a greater sort value, or an equal one with a higher
     * id — the deterministic tiebreaker).
     */
    private function positionBefore(object $row, PublicPageCursor $cursor, bool $searching): bool
    {
        $value = $this->sortValueOf($row, $searching);

        return $value > $cursor->toSortValue()
            || ($value === $cursor->toSortValue() && (int) $row->id > $cursor->toId());
    }

    /**
     * @param  object[]  $rows
     */
    private function nextCursorFor(array $rows, bool $lastBatchFull, bool $searching): ?PublicPageCursor
    {
        $last = $rows[count($rows) - 1] ?? null;

        if ($last === null || ! $lastBatchFull) {
            return null;
        }

        return new PublicPageCursor(
            id: (int) $last->id,
            sortValue: $this->sortValueOf($last, $searching),
        );
    }
}
