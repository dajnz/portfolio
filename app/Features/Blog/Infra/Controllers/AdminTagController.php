<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\AdminTagListQueryDto;
use App\Features\Blog\App\DTOs\TagDataDto;
use App\Features\Blog\App\DTOs\TagDraftDto;
use App\Features\Blog\App\DTOs\TagListItemDto;
use App\Features\Blog\App\UseCases\DestroyTagUseCase;
use App\Features\Blog\App\UseCases\GetTagForEditUseCase;
use App\Features\Blog\App\UseCases\ListTagsUseCase;
use App\Features\Blog\App\UseCases\SearchArticleTagsUseCase;
use App\Features\Blog\App\UseCases\SearchUnifiedBlogTagsUseCase;
use App\Features\Blog\App\UseCases\StoreTagUseCase;
use App\Features\Blog\App\UseCases\UpdateTagUseCase;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\App\DTOs\UnifiedTagOptionDto;
use App\Features\Blog\Domain\Exceptions\ArticleTagNotFoundException;
use App\Features\Blog\Domain\Exceptions\DuplicateArticleTagNameException;
use App\Features\Blog\Infra\Requests\StoreTagApiRequest;
use App\Features\Blog\Infra\Requests\StoreTagRequest;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use App\Features\Shared\Infra\Services\PerLocaleValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Article-tag admin surface plus the inline API creation
 * (`POST /api/tags`): the cursor-paginated tag list (name / slug /
 * article count, cross-locale search), the create/edit forms
 * (per-locale names via LocaleTabs) and the JSON `storeApi` used by the
 * blog form's inline tag creation. The tag↔skill name cross-check
 * surfaces as a `name` validation error.
 * One action → one use case; the controller only translates the HTTP
 * request into app-layer DTOs and the use-case results into the
 * Inertia/JSON output shapes — no repositories, Eloquent or DB calls.
 */
final class AdminTagController
{
    public function __construct(
        private readonly ListTagsUseCase $listCase,
        private readonly GetTagForEditUseCase $editCase,
        private readonly StoreTagUseCase $storeCase,
        private readonly UpdateTagUseCase $updateCase,
        private readonly DestroyTagUseCase $destroyCase,
        private readonly SearchArticleTagsUseCase $searchTagsCase,
        private readonly SearchUnifiedBlogTagsUseCase $searchUnifiedCase,
        private readonly PerLocaleValues $localeValues,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $page = $this->listCase->list($this->buildListQuery($request), App::getLocale());

        return $inertia->render('Blog/Admin/Pages/TagListPage', [
            'tags' => array_map(
                static fn (TagListItemDto $tag): array => self::tagRowProps($tag),
                $page->rows,
            ),
            'meta' => $this->cursorMeta($page->pagination),
        ]);
    }

    public function create(ResponseFactory $inertia): Response
    {
        return $inertia->render('Blog/Admin/Pages/TagFormPage', [
            'tag' => null,
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        try {
            $tag = $this->storeCase->store($this->buildDraft($request));
        } catch (DuplicateArticleTagNameException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => __('admin.tags.name')])]);
        }

        // Redirect to the edit page of the created tag: staying on the
        // create form would keep the client in create mode (Inertia
        // preserves the component across the POST redirect), so the next
        // save would create a duplicate instead of updating.
        return Redirect::to("/admin/tags/{$tag->toId()}/edit")
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function edit(ResponseFactory $inertia, int $tag): Response
    {
        try {
            $data = $this->editCase->get($tag, App::getLocale());
        } catch (ArticleTagNotFoundException) {
            throw new NotFoundHttpException();
        }

        return $inertia->render('Blog/Admin/Pages/TagFormPage', [
            'tag' => $data === null ? null : self::tagEditProps($data),
        ]);
    }

    public function update(StoreTagRequest $request, int $tag): RedirectResponse
    {
        try {
            $this->updateCase->update($tag, $this->buildDraft($request));
        } catch (ArticleTagNotFoundException) {
            throw new NotFoundHttpException();
        } catch (DuplicateArticleTagNameException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => __('admin.tags.name')])]);
        }

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $tag): RedirectResponse
    {
        $this->destroyCase->destroy($tag);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    /**
     * `POST /api/tags` — the inline tag creation of the blog form: 201
     * with the created tag so the frontend can add it to the article's
     * selection without a reload.
     */
    public function storeApi(StoreTagApiRequest $request): JsonResponse
    {
        try {
            $tag = $this->storeCase->store($this->buildApiDraft($request));
        } catch (DuplicateArticleTagNameException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => __('admin.tags.name')])]);
        }

        return ResponseFacade::json([
            'data' => [
                'id' => $tag->toId(),
                'slug' => $tag->toSlug(),
                'name' => $this->nullableNames($tag->namesByLocale()),
            ],
        ], 201);
    }

    /**
     * `GET /api/tags/search`: the article-tags autocomplete —
     * `{id, name, slug}` rows, name ascending, capped at 20.
     */
    public function search(Request $request): JsonResponse
    {
        $tags = $this->searchTagsCase->search(
            trim((string) $request->query('q', '')),
            App::getLocale(),
        );

        return ResponseFacade::json([
            'data' => array_map(
                static fn (TagOptionDto $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ],
                $tags,
            ),
        ]);
    }

    /**
     * `GET /api/blog-tags/search`: the unified autocomplete of the
     * blog filter — article tags and tech skills with the `type`
     * discriminator (`tag` | `skill`), name ascending, capped at 20.
     */
    public function searchUnified(Request $request): JsonResponse
    {
        $options = $this->searchUnifiedCase->search(
            trim((string) $request->query('q', '')),
            App::getLocale(),
        );

        return ResponseFacade::json([
            'data' => array_map(
                static fn (UnifiedTagOptionDto $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'type' => $option->type,
                ],
                $options,
            ),
        ]);
    }

    private function buildListQuery(Request $request): AdminTagListQueryDto
    {
        return new AdminTagListQueryDto(
            search: trim((string) $request->query('q', '')),
            page: max(1, (int) $request->query('page', 1)),
            cursorParams: is_array($request->input('cursor')) ? $request->input('cursor') : null,
            sortColumn: $this->sortColumn($request),
            sortDirection: $this->sortDirection($request),
            url: $request->fullUrl(),
        );
    }

    private function buildDraft(StoreTagRequest $request): TagDraftDto
    {
        return new TagDraftDto(
            submittedSlug: (string) $request->input('slug'),
            names: $this->localeValues->inputMap((array) $request->input('name', [])),
        );
    }

    private function buildApiDraft(StoreTagApiRequest $request): TagDraftDto
    {
        $names = [];
        foreach ((array) $request->input('name', []) as $locale => $name) {
            $names[(string) $locale] = is_string($name) ? $name : null;
        }

        return new TagDraftDto(
            submittedSlug: (string) $request->input('slug'),
            names: $names,
        );
    }

    private function sortColumn(Request $request): ?string
    {
        $column = trim((string) $request->query('sort_column', ''));

        return $column === '' ? null : $column;
    }

    private function sortDirection(Request $request): ?string
    {
        $direction = strtolower(trim((string) $request->query('sort_direction', '')));

        return $direction === '' ? null : $direction;
    }

    /**
     * The `TagTableRowData` wire shape of a list row.
     *
     * @return array<string, mixed>
     */
    private static function tagRowProps(TagListItemDto $tag): array
    {
        return [
            'id' => $tag->id,
            'slug' => $tag->slug,
            'name' => $tag->name,
            'articles_use_count' => $tag->articlesUseCount,
        ];
    }

    /**
     * The `TagEditData` wire shape of the tag form (per-locale names
     * for the LocaleTabs).
     *
     * @return array<string, mixed>
     */
    private static function tagEditProps(TagDataDto $tag): array
    {
        return [
            'id' => $tag->id,
            'slug' => $tag->slug,
            'name' => $tag->name,
            'names' => $tag->names,
        ];
    }

    /**
     * The `TagResponse` `name` map (per-locale, nullable).
     *
     * @param  array<string, string>  $names
     * @return array<string, string|null>
     */
    private function nullableNames(array $names): array
    {
        $map = [];
        foreach (Config::get('portfolio.supported_locales', ['en', 'ru']) as $locale) {
            $map[$locale] = $names[$locale] ?? null;
        }

        return $map;
    }

    /**
     * The admin list `meta` wire shape from the cursor pagination
     * facts.
     *
     * @return array<string, mixed>
     */
    private function cursorMeta(CursorPaginationDto $pagination): array
    {
        return [
            'total' => $pagination->total,
            'per_page' => $pagination->perPage,
            'current_page' => $pagination->currentPage,
            'sort_column' => $pagination->sortColumn,
            'sort_direction' => $pagination->sortDirection,
            'cursors' => [
                'first' => self::cursorProps($pagination->cursors->first),
                'last' => self::cursorProps($pagination->cursors->last),
                'page' => $pagination->cursors->page,
            ],
        ];
    }

    /**
     * @return array{id: int, sort_value: mixed}|null
     */
    private static function cursorProps(?CursorDto $cursor): ?array
    {
        return $cursor === null
            ? null
            : [
                'id' => $cursor->id,
                'sort_value' => $cursor->sortValue,
            ];
    }
}
