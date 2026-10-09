<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Controllers;

use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\ProjectListQuery;
use App\Features\Projects\App\UseCases\GetPublicProjectUseCase;
use App\Features\Projects\App\UseCases\GetPublicProjectsUseCase;
use App\Features\Projects\Infra\Services\ProjectDetailPropsBuilder;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /projects` and `GET /{locale}/projects` — the public projects
 * index: published projects ordered by date with the
 * `?direction=asc|desc` variant and infinite scroll via the opaque
 * composite cursor (the shared {@see PublicCursorCodec}), the `?tags=`
 * AND/intersect and the FTS `?q=` search (which takes over the
 * ordering). `next_cursor` is null when the final batch is incomplete —
 * the exhaustion signal. The JSON dual-response variant
 * (`Accept: application/json` — the autocomplete) returns the
 * length-aware `ProjectsIndexResponse` with raw values, no highlights.
 *
 * `GET /projects/{project:slug}` — the detail page with SEO props +
 * JSON-LD (SoftwareSourceCode + BreadcrumbList), the tech-writeup link
 * gated by the article's current publication state, and the old-slug
 * 301 resolution: main table → `old_slugs` → 301 to the current
 * canonical URL (locale prefix preserved).
 *
 * The controller only translates the HTTP request into app-layer DTOs
 * and the use-case results into the Inertia/JSON output shapes — no
 * repositories, Eloquent or DB calls.
 */
final class PublicProjectController
{
    public function __construct(
        private readonly GetPublicProjectsUseCase $listCase,
        private readonly GetPublicProjectUseCase $detailCase,
        private readonly PublicCursorCodec $cursorCodec,
        private readonly ProjectDetailPropsBuilder $props,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->autocompleteJson($request);
        }

        $result = $this->listCase->get(new ProjectListQuery(
            locale: app()->getLocale(),
            sort: $this->sortFromRequest($request),
            sortDirection: $this->sortDirectionFromRequest($request),
            tagIds: $this->tagIdsFromRequest($request),
            search: $this->searchFromRequest($request),
            cursor: $this->decodeCursor((string) $request->query('cursor', '')),
        ));

        return $inertia->render('Projects/Public/Pages/ProjectsIndexPage', [
            'projects' => $this->cardProps($result),
            'skill_tags' => $result->skillTags,
            'has_more' => $result->nextCursor !== null,
            'next_cursor' => $result->nextCursor === null
                ? null
                : $this->encodeCursor($result->nextCursor),
            'seo' => $this->props->seo($result->seo),
        ]);
    }

    public function show(ResponseFactory $inertia, Request $request): Response|RedirectResponse
    {
        // The route param is read BY NAME from the request — Laravel
        // 13 splices route params into method arguments positionally,
        // and the locale-prefixed route carries `{locale}` first, which
        // would land in a `$project` method parameter.
        $project = (string) $request->route('project');

        $detail = $this->detailCase->get($project, includeUnpublished: false, locale: app()->getLocale());

        if ($detail === null) {
            $currentSlug = $this->detailCase->currentSlugFor($project);

            if ($currentSlug !== null) {
                return redirect()->to($this->projectPath($currentSlug, $request), 301);
            }

            abort(404);
        }

        return $inertia->render('Projects/Public/Pages/ProjectDetailPage', [
            'project' => $this->props->detail($detail->project, $detail->bodies, $detail->techWriteup, app()->getLocale()),
            'seo' => $this->props->seo($detail->seo),
        ]);
    }

    /**
     * The JSON dual-response variant — length-aware pagination
     * (page/per_page), FTS search, tag intersect and the date sort
     * with the direction variant; raw field values without highlights.
     */
    private function autocompleteJson(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $result = $this->listCase->jsonPage(new ProjectListQuery(
            locale: app()->getLocale(),
            sort: $this->sortFromRequest($request),
            sortDirection: $this->sortDirectionFromRequest($request),
            tagIds: $this->tagIdsFromRequest($request),
            search: $this->searchFromRequest($request),
            page: $page,
            perPage: $perPage,
        ));

        return response()->json([
            'data' => $this->jsonItems($result),
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

    /**
     * The `ProjectCardData` wire shape of the index page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cardProps(\App\Features\Projects\App\DTOs\ProjectListDto $result): array
    {
        return array_map(function (ProjectListItemDto $item) use ($result): array {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'brief_description' => $item->briefDescription,
                'published_at' => $item->publishedAt?->format('c'),
                'preview_image' => $item->preview?->url,
                'preview_image_alt' => $item->preview?->altLabels ?? [],
                'github_url' => $item->links->sourceCodeUrl,
                'live_demo_url' => $item->links->liveDemoUrl,
                'is_featured' => $item->isFeatured,
                'tech_tags' => $result->techTags[$item->id] ?? [],
                'screenshots' => array_map(
                    static fn (array $shot): array => [
                        'image_path' => $shot['image_path'],
                        'alt_text' => $shot['alt_text'] ?? [],
                    ],
                    $result->screenshots[$item->id] ?? [],
                ),
            ];
        }, $result->rows);
    }

    /**
     * The raw-value `ProjectsIndexResponse` items of the JSON variant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonItems(\App\Features\Projects\App\DTOs\PaginatedProjectListDto $result): array
    {
        return array_map(function (ProjectListItemDto $item) use ($result): array {
            return [
                'id' => $item->id,
                'slug' => $item->slug,
                'title' => $item->title,
                'brief_description' => $item->briefDescription,
                'published_at' => $item->publishedAt?->format('c'),
                'preview_image' => $item->preview?->url,
                'github_url' => $item->links->sourceCodeUrl,
                'live_demo_url' => $item->links->liveDemoUrl,
                'is_featured' => $item->isFeatured,
                'tech_tags' => $result->techTags[$item->id] ?? [],
            ];
        }, $result->rows);
    }

    /**
     * The 301 target preserving the locale prefix of the request
     * (`/projects/{slug}` vs `/{locale}/projects/{slug}`).
     */
    private function projectPath(string $slug, Request $request): string
    {
        $locale = $request->route('locale');

        return is_string($locale) && $locale !== ''
            ? url('/'.$locale.'/projects/'.$slug)
            : url('/projects/'.$slug);
    }

    private function sortFromRequest(Request $request): string
    {
        // The date column is the only sort strategy; the `?sort=`
        // parameter is kept for contract stability.
        return 'date';
    }

    private function sortDirectionFromRequest(Request $request): string
    {
        return in_array($request->query('direction'), ['asc', 'desc'], true)
            ? (string) $request->query('direction')
            : 'desc';
    }

    /**
     * @return int[]
     */
    private function tagIdsFromRequest(Request $request): array
    {
        return array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            explode(',', (string) $request->query('tags', '')),
        )));
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
