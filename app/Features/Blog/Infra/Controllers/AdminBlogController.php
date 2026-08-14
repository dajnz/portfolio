<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\AdminArticleListQueryDto;
use App\Features\Blog\App\DTOs\ArticleDataDto;
use App\Features\Blog\App\DTOs\ArticleDraftDto;
use App\Features\Blog\App\DTOs\ArticleImageDto;
use App\Features\Blog\App\DTOs\ArticleListItemDto;
use App\Features\Blog\App\DTOs\ArticleOptionDto;
use App\Features\Blog\App\DTOs\ArticleTagOptionDto;
use App\Features\Blog\App\DTOs\ArticleTranslationsDto;
use App\Features\Blog\App\DTOs\RelatedArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\App\UseCases\AdminArticleSearchUseCase;
use App\Features\Blog\App\UseCases\AttachArticleSkillUseCase;
use App\Features\Blog\App\UseCases\AttachRelatedArticleUseCase;
use App\Features\Blog\App\UseCases\DestroyArticleUseCase;
use App\Features\Blog\App\UseCases\DetachArticleSkillUseCase;
use App\Features\Blog\App\UseCases\DetachRelatedArticleUseCase;
use App\Features\Blog\App\UseCases\ForceDestroyArticleUseCase;
use App\Features\Blog\App\UseCases\GetArticleForEditUseCase;
use App\Features\Blog\App\UseCases\GetArticleListItemUseCase;
use App\Features\Blog\App\UseCases\ListArticlesUseCase;
use App\Features\Blog\App\UseCases\RestoreArticleUseCase;
use App\Features\Blog\App\UseCases\StoreArticleUseCase;
use App\Features\Blog\App\UseCases\UpdateArticlePublicationUseCase;
use App\Features\Blog\App\UseCases\UpdateArticleUseCase;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\Exceptions\ArticleSkillAlreadyAttachedException;
use App\Features\Blog\Domain\Exceptions\ArticleSlugOccupiedException;
use App\Features\Blog\Domain\Exceptions\RelatedArticleAlreadyLinkedException;
use App\Features\Blog\Domain\Exceptions\RelatedArticleNotLinkableException;
use App\Features\Blog\Infra\Requests\AttachArticleSkillRequest;
use App\Features\Blog\Infra\Requests\AttachRelatedArticleRequest;
use App\Features\Blog\Infra\Requests\StoreArticleRequest;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use App\Features\Shared\App\DTOs\PaginationDto;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Infra\Requests\BulkActionRequest;
use App\Features\Shared\Infra\Services\PerLocaleValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Blog admin surface (routes.md:110-128): the cursor-paginated list
 * with its filter bar (search / article tags / skill tags / published
 * status / year / trashed — the dual-response JSON autocomplete
 * variant per admin-section-ui.md:539-572), the create/edit forms
 * (per-locale maps for the translatable fields — the LocaleTabs
 * convention), the CRUD + soft-delete lifecycle, the bulk actions and
 * the quick actions (publish/unpublish, related attach/detach, skill
 * attach/detach — returning the updated row as JSON so the list row
 * updates without a reload, content-management.md:255).
 * One action → one use case; the controller only translates the HTTP
 * request into app-layer DTOs and the use-case results into the
 * Inertia/JSON output shapes — no repositories, Eloquent or DB calls.
 */
final class AdminBlogController
{
    public function __construct(
        private readonly ListArticlesUseCase $listCase,
        private readonly GetArticleForEditUseCase $editCase,
        private readonly StoreArticleUseCase $storeCase,
        private readonly UpdateArticleUseCase $updateCase,
        private readonly DestroyArticleUseCase $destroyCase,
        private readonly RestoreArticleUseCase $restoreCase,
        private readonly ForceDestroyArticleUseCase $forceDestroyCase,
        private readonly UpdateArticlePublicationUseCase $publicationCase,
        private readonly AttachRelatedArticleUseCase $attachRelatedCase,
        private readonly DetachRelatedArticleUseCase $detachRelatedCase,
        private readonly AttachArticleSkillUseCase $attachSkillCase,
        private readonly DetachArticleSkillUseCase $detachSkillCase,
        private readonly AdminArticleSearchUseCase $searchCase,
        private readonly GetArticleListItemUseCase $itemCase,
        private readonly PerLocaleValues $localeValues,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->autocompleteJson($request);
        }

        $page = $this->listCase->list($this->buildListQuery($request));

        return $inertia->render('Blog/Admin/Pages/BlogListPage', [
            'articles' => array_map(
                static fn (ArticleListItemDto $item): array => self::listItemProps($item),
                $page->rows,
            ),
            'meta' => $this->cursorMeta($page->pagination),
            'tags' => $this->tagProps($page->tags),
            'years' => $page->years,
        ]);
    }

    public function create(ResponseFactory $inertia): Response
    {
        return $this->formPage($inertia, null);
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        try {
            $this->storeCase->store($this->buildDraft($request));
        } catch (ArticleSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        } catch (RelatedArticleNotLinkableException $e) {
            throw ValidationException::withMessages(['related_articles' => $e->getMessage()]);
        }

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function edit(ResponseFactory $inertia, int $article): Response
    {
        return $this->formPage($inertia, $article);
    }

    public function update(StoreArticleRequest $request, int $article): RedirectResponse
    {
        try {
            $this->updateCase->update($article, $this->buildDraft($request));
        } catch (ArticleNotFoundException) {
            throw new NotFoundHttpException();
        } catch (ArticleSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        } catch (RelatedArticleNotLinkableException $e) {
            throw ValidationException::withMessages(['related_articles' => $e->getMessage()]);
        }

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $article): RedirectResponse
    {
        $this->destroyCase->destroy($article);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function restore(Request $request, int $article): RedirectResponse
    {
        try {
            $this->restoreCase->restore($article);
        } catch (ArticleSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.restored')]);
    }

    public function forceDestroy(Request $request, int $article): RedirectResponse
    {
        $this->forceDestroyCase->forceDestroy($article);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function publish(Request $request, int $article): JsonResponse
    {
        return $this->publicationToggle($article, true);
    }

    public function unpublish(Request $request, int $article): JsonResponse
    {
        return $this->publicationToggle($article, false);
    }

    public function attachRelated(AttachRelatedArticleRequest $request, int $article, int $relatedArticle): JsonResponse
    {
        try {
            $this->attachRelatedCase->attach($article, $relatedArticle);
        } catch (RelatedArticleAlreadyLinkedException $e) {
            return ResponseFacade::json(['message' => $e->getMessage()], 409);
        }

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($article))]);
    }

    public function detachRelated(Request $request, int $article, int $relatedArticle): JsonResponse
    {
        $this->detachRelatedCase->detach($article, $relatedArticle);

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($article))]);
    }

    public function attachSkill(AttachArticleSkillRequest $request, int $article, int $skill): JsonResponse
    {
        try {
            $this->attachSkillCase->attach($article, $skill);
        } catch (ArticleSkillAlreadyAttachedException $e) {
            return ResponseFacade::json(['message' => $e->getMessage()], 409);
        }

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($article))]);
    }

    public function detachSkill(Request $request, int $article, int $skill): JsonResponse
    {
        $this->detachSkillCase->detach($article, $skill);

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($article))]);
    }

    public function bulkPublish(BulkActionRequest $request): JsonResponse
    {
        return $this->bulkPublication($request, true);
    }

    public function bulkUnpublish(BulkActionRequest $request): JsonResponse
    {
        return $this->bulkPublication($request, false);
    }

    public function bulkDestroy(BulkActionRequest $request): JsonResponse
    {
        $this->destroyCase->destroyMany($this->ids($request));

        return ResponseFacade::json();
    }

    public function bulkRestore(BulkActionRequest $request): JsonResponse
    {
        try {
            $this->restoreCase->restoreMany($this->ids($request));
        } catch (ArticleSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        return ResponseFacade::json();
    }

    public function bulkForceDestroy(BulkActionRequest $request): JsonResponse
    {
        $this->forceDestroyCase->forceDestroyMany($this->ids($request));

        return ResponseFacade::json();
    }

    /**
     * The JSON autocomplete variant (admin-section-ui.md:539-572):
     * ILIKE title search over all locales, unpublished included,
     * length-aware pagination.
     */
    private function autocompleteJson(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));

        $result = $this->searchCase->search($search, $page, $perPage);

        return ResponseFacade::json([
            'data' => array_map(
                static fn (ArticleListItemDto $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'published_at' => $item->publishedAt?->format('c'),
                    'is_published' => $item->isPublished,
                    'created_at' => $item->createdAt->format('c'),
                ],
                $result->rows,
            ),
            'meta' => $this->lengthAwareMeta($result->pagination),
        ]);
    }

    private function formPage(ResponseFactory $inertia, ?int $articleId): Response
    {
        $data = $this->editCase->get($articleId, App::getLocale());

        return $inertia->render('Blog/Admin/Pages/BlogFormPage', [
            'article' => $data->article === null ? null : self::editProps($data->article),
            'tags' => $this->tagProps($data->tags),
            'articles' => array_map(
                static fn (ArticleOptionDto $article): array => self::articleOptionProps($article),
                $data->articles,
            ),
            'preview_url' => $data->previewUrl,
        ]);
    }

    /**
     * The app-layer admin list query — the raw query values are
     * extracted here, the paginator parsing happens in the infra
     * paginator implementation.
     */
    private function buildListQuery(Request $request): AdminArticleListQueryDto
    {
        $published = $request->query('published');

        return new AdminArticleListQueryDto(
            search: trim((string) $request->query('q', '')),
            articleTagIds: $this->idList($request->query('article_tags', '')),
            skillTagIds: $this->idList($request->query('skill_tags', '')),
            published: $published === '0' || $published === 'false'
                ? false
                : ($published === '1' || $published === 'true' ? true : null),
            year: $request->query('year') === null || $request->query('year') === ''
                ? null
                : (int) $request->query('year'),
            trashed: $request->boolean('trashed'),
            page: max(1, (int) $request->query('page', 1)),
            cursorParams: is_array($request->input('cursor')) ? $request->input('cursor') : null,
            sortColumn: $this->sortColumn($request),
            sortDirection: $this->sortDirection($request),
            url: $request->fullUrl(),
        );
    }

    /**
     * The app-layer draft DTO of the store/update actions — the
     * validated request values, normalized into the per-locale maps
     * and the id arrays.
     */
    private function buildDraft(StoreArticleRequest $request): ArticleDraftDto
    {
        $leadImage = $request->input('lead_image');

        return new ArticleDraftDto(
            submittedSlug: (string) $request->input('slug'),
            translations: new ArticleTranslationsDto(
                titles: $this->localeValues->inputMap((array) $request->input('title', [])),
                shortDescriptions: $this->localeValues->inputMap((array) $request->input('short_description', [])),
                contents: $this->localeValues->inputMap((array) $request->input('content', [])),
            ),
            leadImage: is_string($leadImage) && $leadImage !== ''
                ? new ArticleImageDto(
                    url: $leadImage,
                    altLabels: $this->nullableLocaleMap((array) $request->input('lead_image_alt', [])),
                )
                : null,
            isFeatured: $request->boolean('is_featured'),
            isPublished: $request->boolean('is_published'),
            tagIds: array_map('intval', (array) $request->input('tags', [])),
            skillTagIds: array_map('intval', (array) $request->input('skill_tags', [])),
            relatedArticleIds: array_map('intval', (array) $request->input('related_articles', [])),
        );
    }

    /**
     * @param  array<int|string, mixed>  $input
     * @return array<string, string|null>
     */
    private function nullableLocaleMap(array $input): array
    {
        $map = [];
        foreach ($input as $locale => $value) {
            $map[(string) $locale] = is_string($value) ? $value : null;
        }

        return $map;
    }

    /**
     * @return int[]
     */
    private function idList(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            explode(',', $raw),
        )));
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

    private function publicationToggle(int $articleId, bool $published): JsonResponse
    {
        $this->publicationCase->set($articleId, $published);

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($articleId))]);
    }

    private function fetchItem(int $articleId): ArticleListItemDto
    {
        try {
            return $this->itemCase->get($articleId);
        } catch (ArticleNotFoundException) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * @return int[]
     */
    private function ids(BulkActionRequest $request): array
    {
        return array_map('intval', $request->validated('ids'));
    }

    private function bulkPublication(BulkActionRequest $request, bool $published): JsonResponse
    {
        $this->publicationCase->setMany($this->ids($request), $published);

        return ResponseFacade::json();
    }

    /**
     * The `ArticleTableRowData` wire shape of a list item.
     *
     * @return array<string, mixed>
     */
    private static function listItemProps(ArticleListItemDto $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'is_published' => $item->isPublished,
            'published_at' => $item->publishedAt?->format('c'),
            'reading_time_minutes' => $item->readingTimeMinutes,
            'views' => $item->views,
            'lead_image' => $item->leadImage,
            'related_count' => $item->relatedCount,
            'created_at' => $item->createdAt->format('c'),
            'updated_at' => $item->updatedAt->format('c'),
            'tags' => array_map(
                static fn (ArticleTagOptionDto $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'type' => $tag->type,
                ],
                $item->tags,
            ),
        ];
    }

    /**
     * The `ArticleEditData` wire shape of the stored article data.
     *
     * @return array<string, mixed>
     */
    private static function editProps(ArticleDataDto $article): array
    {
        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->translations->titles,
            'short_description' => $article->translations->shortDescriptions,
            'content' => $article->translations->contents,
            'published_at' => $article->publishedAt?->format('c'),
            'reading_time_minutes' => $article->readingTimeMinutes,
            'lead_image' => $article->leadImage?->url,
            'lead_image_alt' => $article->leadImage?->altLabels ?? [],
            'is_featured' => $article->isFeatured,
            'is_published' => $article->isPublished,
            // The tag DTOs carry the pivot ids (the review's "tags DTO
            // includes IDs" point) — the related-ids wire value is
            // derived from the related rows.
            'related_article_ids' => array_map(
                static fn (RelatedArticleDto $related): int => $related->id,
                $article->related,
            ),
            'tags' => array_map(
                static fn (TagOptionDto $tag): array => self::tagDataProps($tag),
                $article->tags,
            ),
            'skill_tags' => array_map(
                static fn (TechSkillTag $skill): array => [
                    'id' => $skill->toId(),
                    'name' => $skill->toSkillName(),
                    'icon' => $skill->toIcon(),
                ],
                $article->skillTags,
            ),
            'related' => array_map(
                static fn (RelatedArticleDto $related): array => self::relatedProps($related),
                $article->related,
            ),
        ];
    }

    /**
     * The `TagData` wire shape of a single tag — `{id, slug, name}`
     * with the locale-resolved name.
     *
     * @return array<string, mixed>
     */
    private static function tagDataProps(TagOptionDto $tag): array
    {
        return [
            'id' => $tag->id,
            'slug' => $tag->slug,
            'name' => $tag->name,
        ];
    }

    /**
     * The related-article row — `{id, title}`.
     *
     * @return array<string, mixed>
     */
    private static function relatedProps(RelatedArticleDto $related): array
    {
        return [
            'id' => $related->id,
            'title' => $related->title,
        ];
    }

    /**
     * The published-article picker option — `{id, slug, title}`.
     *
     * @return array<string, mixed>
     */
    private static function articleOptionProps(ArticleOptionDto $article): array
    {
        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
        ];
    }

    /**
     * The `TagData[]` picker options of the list/form pages.
     *
     * @param  TagOptionDto[]  $tags
     * @return array<int, array<string, mixed>>
     */
    private function tagProps(array $tags): array
    {
        return array_map(
            static fn (TagOptionDto $tag): array => self::tagDataProps($tag),
            $tags,
        );
    }

    /**
     * The admin list `meta` wire shape from the cursor pagination
     * facts (admin-section-ui.md:169-181).
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
     * The length-aware `meta` wire shape of the JSON autocomplete
     * variants (admin-section-ui.md:539-572, routes.md:172-176).
     *
     * @return array<string, mixed>
     */
    private function lengthAwareMeta(PaginationDto $pagination): array
    {
        return [
            'current_page' => $pagination->currentPage,
            'from' => $pagination->from,
            'to' => $pagination->to,
            'per_page' => $pagination->perPage,
            'last_page' => $pagination->lastPage,
            'total' => $pagination->total,
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
