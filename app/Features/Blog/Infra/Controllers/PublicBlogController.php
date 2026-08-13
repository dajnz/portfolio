<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\ArticleCardDto;
use App\Features\Blog\App\DTOs\BlogSeoDto;
use App\Features\Blog\App\DTOs\PublicArticleDto;
use App\Features\Blog\App\DTOs\PublicBlogListQuery;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\App\UseCases\GetPublicArticleUseCase;
use App\Features\Blog\App\UseCases\GetPublicBlogIndexUseCase;
use App\Features\Blog\App\UseCases\ToggleArticleReactionUseCase;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\ValueObjects\ReactionType;
use App\Features\Blog\Infra\Jobs\IncrementArticleViewJob;
use App\Features\Blog\Infra\Requests\StoreReactionRequest;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /blog` and `GET /{locale}/blog` (routes.md:27,55) — the public
 * blog index (public-section-ui.md:209-238): published articles
 * ordered by `published_at` with infinite scroll via the opaque
 * composite cursor (the shared {@see PublicCursorCodec}), the
 * `?article_tags=` / `?skill_tags=` AND/intersect filters (B2), the
 * `?year=` filter and the FTS `?q=` search — the Inertia HTML variant
 * carries the `ts_headline()` `<mark>` snippets (B16). The JSON
 * dual-response variant (`Accept: application/json` — the search
 * autocomplete, routes.md:199-212) returns the length-aware
 * `BlogIndexResponse` with raw values and the prefix search form.
 *
 * `GET /blog/{article:slug}` — the detail page (routes.md:28,56)
 * with the SEO props + JSON-LD (BlogPosting + BreadcrumbList), the
 * queued view-counter job (content-management.md:214-218), the
 * feature-flagged reactions and the old-slug 301 resolution (B5/B6):
 * main table → `old_slugs` → 301 to the current canonical URL
 * (locale prefix preserved). The route param is read BY NAME from
 * the request (B178 — Laravel 13 splices route params positionally).
 *
 * `POST /api/blog/{article}/reaction` — the reaction toggle
 * (feature-flagged, routes.md:190): the server generates the
 * fingerprint (keyed HMAC of ip|user_agent, B4), the use case
 * upserts/undoes, and the response carries the updated state.
 *
 * The controller only translates the HTTP request into app-layer
 * DTOs and the use-case results into the Inertia/JSON output shapes —
 * no repositories, Eloquent or DB calls.
 */
final class PublicBlogController
{
    public function __construct(
        private readonly GetPublicBlogIndexUseCase $listCase,
        private readonly GetPublicArticleUseCase $detailCase,
        private readonly ToggleArticleReactionUseCase $reactionCase,
        private readonly PublicCursorCodec $cursorCodec,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->autocompleteJson($request);
        }

        $result = $this->listCase->get($this->buildListQuery($request));

        return $inertia->render('Blog/Public/Pages/BlogIndexPage', [
            'articles' => array_map(
                fn (ArticleCardDto $card): array => self::cardProps($card, useSnippet: true),
                $result->articles,
            ),
            'tags' => array_map(
                static fn (TagOptionDto $tag): array => self::tagProps($tag),
                $result->tags,
            ),
            'years' => $result->years,
            'has_more' => $result->nextCursor !== null,
            'next_cursor' => $result->nextCursor === null
                ? null
                : $this->encodeCursor($result->nextCursor),
            'seo' => self::seoProps($result->seo),
        ]);
    }

    public function show(ResponseFactory $inertia, Request $request): Response|RedirectResponse
    {
        $slug = (string) $request->route('article');

        $detail = $this->detailCase->get(
            $slug,
            includeUnpublished: false,
            locale: app()->getLocale(),
            fingerprint: $this->fingerprint($request),
        );

        if ($detail === null) {
            $currentSlug = $this->detailCase->currentSlugFor($slug);

            if ($currentSlug !== null) {
                return redirect()->to($this->articlePath($currentSlug, $request), 301);
            }

            abort(404);
        }

        dispatch(new IncrementArticleViewJob(
            articleId: $detail->id,
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        ));

        return $inertia->render('Blog/Public/Pages/BlogArticlePage', [
            'article' => self::detailProps($detail),
            'reactions' => $detail->reactions,
            'seo' => self::seoProps($detail->seo),
        ]);
    }

    public function reaction(StoreReactionRequest $request): JsonResponse
    {
        try {
            $outcome = $this->reactionCase->toggle(
                articleId: (int) $request->route('article'),
                reaction: ReactionType::from($request->validated('reaction')),
                fingerprint: $this->fingerprint($request),
            );
        } catch (ArticleNotFoundException) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'reaction' => $outcome->reaction?->value,
                'likes' => $outcome->likes,
                'dislikes' => $outcome->dislikes,
            ],
        ]);
    }

    /**
     * The JSON dual-response variant (routes.md:199-212) — length-aware
     * pagination, the tag intersects, the year filter and the prefix
     * autocomplete search; raw field values without highlights (B16).
     */
    private function autocompleteJson(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->listCase->jsonPage($this->buildListQuery($request, $page, $perPage));

        return response()->json([
            'data' => array_map(
                fn (ArticleCardDto $card): array => self::cardProps($card, useSnippet: false),
                $result->articles,
            ),
            'meta' => [
                'current_page' => $result->pagination->currentPage,
                'from' => $result->pagination->from,
                'to' => $result->pagination->to,
                'per_page' => $result->pagination->perPage,
                'last_page' => $result->pagination->lastPage,
                'total' => $result->pagination->total,
            ],
        ]);
    }

    private function buildListQuery(Request $request, ?int $page = null, ?int $perPage = null): PublicBlogListQuery
    {
        return new PublicBlogListQuery(
            locale: app()->getLocale(),
            articleTagIds: $this->idListFromRequest($request, 'article_tags'),
            skillTagIds: $this->idListFromRequest($request, 'skill_tags'),
            year: $this->yearFromRequest($request),
            search: $this->searchFromRequest($request),
            cursor: $this->decodeCursor((string) $request->query('cursor', '')),
            page: $page ?? max(1, (int) $request->query('page', 1)),
            perPage: $perPage ?? max(1, min(50, (int) $request->query('per_page', 10))),
        );
    }

    /**
     * The `ArticleCardData` wire shape of a card — the snippet
     * substitutes the `short_description` in the HTML variant's search
     * results (B16); the JSON variant returns the raw value.
     *
     * @return array<string, mixed>
     */
    private static function cardProps(ArticleCardDto $card, bool $useSnippet): array
    {
        return [
            'id' => $card->id,
            'title' => $card->title,
            'slug' => $card->slug,
            'short_description' => $useSnippet && $card->snippet !== null
                ? $card->snippet
                : $card->shortDescription,
            'published_at' => $card->publishedAt?->format('c'),
            'reading_time_minutes' => $card->readingTimeMinutes,
            'lead_image' => $card->leadImage,
            'tags' => array_map(
                static fn (TagOptionDto $tag): array => self::tagProps($tag),
                $card->tags,
            ),
        ];
    }

    /**
     * The `ArticleDetailData` wire shape of the article page.
     *
     * @return array<string, mixed>
     */
    private static function detailProps(PublicArticleDto $detail): array
    {
        return [
            'id' => $detail->id,
            'title' => $detail->title,
            'slug' => $detail->slug,
            'content_html' => $detail->contentHtml,
            'short_description' => $detail->shortDescription,
            'published_at' => $detail->publishedAt?->format('c'),
            'reading_time_minutes' => $detail->readingTimeMinutes,
            'lead_image' => $detail->leadImage?->url,
            'lead_image_alt' => $detail->leadImage?->altLabelsMap() ?? [],
            'tags' => array_map(
                static fn (TagOptionDto $tag): array => self::tagProps($tag),
                $detail->tags,
            ),
            'related' => array_map(
                static fn (ArticleCardDto $card): array => self::cardProps($card, useSnippet: false),
                $detail->related,
            ),
            'views_count' => $detail->views,
        ];
    }

    /**
     * The `TagData` wire shape — `{id, slug, name}`.
     *
     * @return array<string, mixed>
     */
    private static function tagProps(TagOptionDto $tag): array
    {
        return [
            'id' => $tag->id,
            'slug' => $tag->slug,
            'name' => $tag->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function seoProps(BlogSeoDto $seo): array
    {
        return [
            'title' => $seo->title,
            'description' => $seo->description,
            'canonical_url' => $seo->canonicalUrl,
            'og_type' => $seo->ogType,
            'og_image' => $seo->ogImage,
            'json_ld' => $seo->jsonLd,
            'robots' => $seo->robots,
        ];
    }

    /**
     * The keyed-HMAC visitor fingerprint (content-management.md:212,
     * B4): `hash_hmac('sha256', ip.'|'.user_agent, key)`.
     */
    private function fingerprint(Request $request): string
    {
        $key = config('analytics.ip_hash_key') ?: config('app.key');

        return hash_hmac('sha256', (string) $request->ip().'|'.$request->userAgent(), (string) $key);
    }

    /**
     * The 301 target preserving the locale prefix of the request
     * (`/blog/{slug}` vs `/{locale}/blog/{slug}`).
     */
    private function articlePath(string $slug, Request $request): string
    {
        $locale = $request->route('locale');

        return is_string($locale) && $locale !== ''
            ? url('/'.$locale.'/blog/'.$slug)
            : url('/blog/'.$slug);
    }

    /**
     * @return int[]
     */
    private function idListFromRequest(Request $request, string $parameter): array
    {
        return array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            explode(',', (string) $request->query($parameter, '')),
        )));
    }

    private function yearFromRequest(Request $request): ?int
    {
        $year = filter_var($request->query('year'), FILTER_VALIDATE_INT);

        return $year === false ? null : (int) $year;
    }

    private function searchFromRequest(Request $request): ?string
    {
        $search = trim((string) $request->query('q', ''));

        return $search === '' ? null : mb_substr($search, 0, 200);
    }

    private function decodeCursor(string $cursor): ?PublicPageCursor
    {
        $decoded = $this->cursorCodec->decode($cursor);

        return $decoded === null
            ? null
            : new PublicPageCursor(id: $decoded->id, sortValue: (string) $decoded->sortValue);
    }

    private function encodeCursor(PublicPageCursor $cursor): string
    {
        return $this->cursorCodec->encode(new Cursor(
            id: $cursor->toId(),
            sortValue: $cursor->toSortValue(),
        ));
    }
}
