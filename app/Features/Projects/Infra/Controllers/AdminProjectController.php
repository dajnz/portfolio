<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Controllers;

use App\Features\Projects\App\DTOs\AdminProjectListQuery;
use App\Features\Projects\App\DTOs\ProjectDataDto;
use App\Features\Projects\App\DTOs\ProjectDraftDto;
use App\Features\Projects\App\DTOs\ProjectEngineeringDetailDto;
use App\Features\Projects\App\DTOs\ProjectImageDto;
use App\Features\Projects\App\DTOs\ProjectLinksDto;
use App\Features\Projects\App\DTOs\ProjectListItemDto;
use App\Features\Projects\App\DTOs\ProjectScreenshotDto;
use App\Features\Projects\App\DTOs\ProjectTranslationsDto;
use App\Features\Projects\App\DTOs\SkillOptionDto;
use App\Features\Projects\App\UseCases\AdminProjectSearchUseCase;
use App\Features\Projects\App\UseCases\AttachProjectTagUseCase;
use App\Features\Projects\App\UseCases\DestroyProjectUseCase;
use App\Features\Projects\App\UseCases\DetachProjectTagUseCase;
use App\Features\Projects\App\UseCases\ForceDestroyProjectUseCase;
use App\Features\Projects\App\UseCases\GetProjectForEditUseCase;
use App\Features\Projects\App\UseCases\GetProjectListItemUseCase;
use App\Features\Projects\App\UseCases\ListProjectsUseCase;
use App\Features\Projects\App\UseCases\ReorderFeaturedProjectsUseCase;
use App\Features\Projects\App\UseCases\RestoreProjectUseCase;
use App\Features\Projects\App\UseCases\StoreProjectUseCase;
use App\Features\Projects\App\UseCases\UpdateProjectFeaturedUseCase;
use App\Features\Projects\App\UseCases\UpdateProjectPublicationUseCase;
use App\Features\Projects\App\UseCases\UpdateProjectTechWriteupUseCase;
use App\Features\Projects\App\UseCases\UpdateProjectUseCase;
use App\Features\Projects\Domain\Exceptions\ProjectNotFoundException;
use App\Features\Projects\Domain\Exceptions\ProjectSlugOccupiedException;
use App\Features\Projects\Domain\Exceptions\ProjectTagAlreadyAttachedException;
use App\Features\Projects\Infra\Requests\AttachProjectTagRequest;
use App\Features\Projects\Infra\Requests\StoreProjectRequest;
use App\Features\Projects\Infra\Requests\UpdateTechWriteupRequest;
use App\Features\Shared\Domain\ValueObjects\BlogArticleId;
use App\Features\Shared\Infra\Requests\BulkActionRequest;
use App\Features\Shared\Infra\Services\PerLocaleValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Projects admin surface: the cursor-paginated list with its filter
 * bar (search / featured / tech tags / published / trashed — the
 * dual-response JSON autocomplete variant), the create/edit forms
 * (per-locale maps for the translatable fields — the LocaleTabs
 * convention), the CRUD + soft-delete lifecycle, the bulk actions and
 * the quick actions (feature/unfeature, featured reorder, tech writeup,
 * tag attach/detach, publish/unpublish — returning the updated row as
 * JSON so the list row updates without a reload). One action → one use
 * case; the controller only translates the HTTP request into app-layer
 * DTOs and the use-case results into the Inertia/JSON output shapes —
 * no repositories, Eloquent or DB calls.
 */
final class AdminProjectController
{
    public function __construct(
        private readonly ListProjectsUseCase $listCase,
        private readonly GetProjectForEditUseCase $editCase,
        private readonly StoreProjectUseCase $storeCase,
        private readonly UpdateProjectUseCase $updateCase,
        private readonly DestroyProjectUseCase $destroyCase,
        private readonly RestoreProjectUseCase $restoreCase,
        private readonly ForceDestroyProjectUseCase $forceDestroyCase,
        private readonly UpdateProjectPublicationUseCase $publicationCase,
        private readonly UpdateProjectFeaturedUseCase $featuredCase,
        private readonly ReorderFeaturedProjectsUseCase $reorderCase,
        private readonly UpdateProjectTechWriteupUseCase $techWriteupCase,
        private readonly AttachProjectTagUseCase $attachTagCase,
        private readonly DetachProjectTagUseCase $detachTagCase,
        private readonly AdminProjectSearchUseCase $searchCase,
        private readonly GetProjectListItemUseCase $itemCase,
        private readonly PerLocaleValues $localeValues,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->autocompleteJson($request);
        }

        $page = $this->listCase->list($this->buildListQuery($request));

        return $inertia->render('Projects/Admin/Pages/ProjectsListPage', [
            'projects' => array_map(
                static fn (ProjectListItemDto $item): array => self::listItemProps($item),
                $page->rows,
            ),
            'featured_projects' => array_map(
                static fn (ProjectListItemDto $item): array => self::listItemProps($item),
                $page->featuredProjects,
            ),
            'meta' => $this->cursorMeta($page->pagination),
        ]);
    }

    public function create(ResponseFactory $inertia): Response
    {
        return $this->formPage($inertia, null);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->storeCase->store($this->buildDraft($request));

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function edit(ResponseFactory $inertia, int $project): Response
    {
        return $this->formPage($inertia, $project);
    }

    public function update(StoreProjectRequest $request, int $project): RedirectResponse
    {
        try {
            $this->updateCase->update($project, $this->buildDraft($request));
        } catch (ProjectNotFoundException) {
            abort(404);
        }

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        $this->destroyCase->destroy($project);

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function restore(Request $request, int $project): RedirectResponse
    {
        try {
            $this->restoreCase->restore($project);
        } catch (ProjectSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.restored')]);
    }

    public function forceDestroy(Request $request, int $project): RedirectResponse
    {
        $this->forceDestroyCase->forceDestroy($project);

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function reorderFeatured(BulkActionRequest $request): JsonResponse
    {
        $this->reorderCase->reorder(array_map('intval', $request->validated('ids')));

        return response()->json();
    }

    public function feature(Request $request, int $project): JsonResponse
    {
        return $this->featuredToggle($project, true);
    }

    public function unfeature(Request $request, int $project): JsonResponse
    {
        return $this->featuredToggle($project, false);
    }

    public function updateTechWriteup(UpdateTechWriteupRequest $request, int $project): JsonResponse
    {
        $articleId = $request->input('tech_writeup_article_id');

        $this->techWriteupCase->update(
            $project,
            $articleId === null ? null : new BlogArticleId((int) $articleId),
        );

        return response()->json(['data' => self::listItemProps($this->fetchItem($project))]);
    }

    public function attachTag(AttachProjectTagRequest $request, int $project): JsonResponse
    {
        try {
            $this->attachTagCase->attach($project, (int) $request->validated('skill_id'));
        } catch (ProjectTagAlreadyAttachedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => self::listItemProps($this->fetchItem($project))]);
    }

    public function detachTag(Request $request, int $project, int $skill): JsonResponse
    {
        $this->detachTagCase->detach($project, $skill);

        return response()->json(['data' => self::listItemProps($this->fetchItem($project))]);
    }

    public function publish(Request $request, int $project): JsonResponse
    {
        return $this->publicationToggle($project, true);
    }

    public function unpublish(Request $request, int $project): JsonResponse
    {
        return $this->publicationToggle($project, false);
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

        return response()->json();
    }

    public function bulkRestore(BulkActionRequest $request): JsonResponse
    {
        try {
            $this->restoreCase->restoreMany($this->ids($request));
        } catch (ProjectSlugOccupiedException $e) {
            throw ValidationException::withMessages(['slug' => $e->getMessage()]);
        }

        return response()->json();
    }

    public function bulkForceDestroy(BulkActionRequest $request): JsonResponse
    {
        $this->forceDestroyCase->forceDestroyMany($this->ids($request));

        return response()->json();
    }

    /**
     * The JSON autocomplete variant: ILIKE title search over all
     * locales, unpublished included, length-aware pagination.
     */
    private function autocompleteJson(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));

        $result = $this->searchCase->search($search, $page, $perPage);

        return response()->json([
            'data' => array_map(
                static fn (ProjectListItemDto $item): array => [
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

    private function formPage(ResponseFactory $inertia, ?int $projectId): Response
    {
        $data = $this->editCase->get($projectId, app()->getLocale());

        return $inertia->render('Projects/Admin/Pages/ProjectFormPage', [
            'project' => $data->project === null ? null : self::editProps($data->project),
            'skills' => $data->skills,
            'articles' => array_map(
                static fn ($article): array => [
                    'id' => $article->id,
                    'slug' => $article->slug,
                    'title' => $article->title,
                ],
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
    private function buildListQuery(Request $request): AdminProjectListQuery
    {
        $published = $request->query('published');

        return new AdminProjectListQuery(
            search: trim((string) $request->query('q', '')),
            featured: $request->boolean('featured'),
            tags: $this->tagIds($request),
            published: $published === '0' || $published === 'false'
                ? false
                : ($published === '1' || $published === 'true' ? true : null),
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
     * and the nested row DTOs.
     */
    private function buildDraft(StoreProjectRequest $request): ProjectDraftDto
    {
        $isFeatured = $request->boolean('is_featured');
        $techWriteupArticleId = $request->input('tech_writeup_article_id');

        return new ProjectDraftDto(
            submittedSlug: (string) $request->input('slug'),
            translations: new ProjectTranslationsDto(
                titles: $this->localeValues->inputMap((array) $request->input('title', [])),
                briefDescriptions: $this->localeValues->inputMap((array) $request->input('brief_description', [])),
                fullDescriptions: $this->nullableLocaleMap((array) $request->input('full_description', [])),
                problems: $this->nullableLocaleMap((array) $request->input('problem', [])),
                solutions: $this->nullableLocaleMap((array) $request->input('solution', [])),
            ),
            preview: $this->buildPreview($request),
            screenshots: $this->buildScreenshots((array) $request->input('screenshots', [])),
            engineeringDetails: $this->buildEngineeringDetails((array) $request->input('detail_items', [])),
            links: new ProjectLinksDto(
                sourceCodeUrl: $request->input('github_url'),
                liveDemoUrl: $request->input('live_demo_url'),
            ),
            isFeatured: $isFeatured,
            featuredSortOrder: $isFeatured && $request->input('featured_sort_order') !== null
                ? (int) $request->input('featured_sort_order')
                : null,
            isPublished: $request->boolean('is_published'),
            techTagIds: array_map('intval', (array) $request->input('tech_tags', [])),
            techWriteupArticleId: $techWriteupArticleId === null
                ? null
                : new BlogArticleId((int) $techWriteupArticleId),
        );
    }

    private function buildPreview(StoreProjectRequest $request): ?ProjectImageDto
    {
        $image = $request->input('preview_image');

        return is_string($image) && $image !== ''
            ? new ProjectImageDto(
                url: $image,
                altLabels: $this->nullableLocaleMap((array) $request->input('preview_image_alt', [])),
            )
            : null;
    }

    /**
     * @param  array<int, mixed>  $screenshots
     * @return ProjectScreenshotDto[]
     */
    private function buildScreenshots(array $screenshots): array
    {
        return array_map(function (mixed $screenshot): ProjectScreenshotDto {
            $screenshot = (array) $screenshot;

            return new ProjectScreenshotDto(
                id: isset($screenshot['id']) && is_numeric($screenshot['id'])
                    ? (int) $screenshot['id']
                    : null,
                image: new ProjectImageDto(
                    url: $screenshot['image'] ?? null,
                    altLabels: $this->nullableLocaleMap((array) ($screenshot['alt_text'] ?? [])),
                    descriptions: $this->nullableLocaleMap((array) ($screenshot['description'] ?? [])),
                ),
                titles: $this->nullableLocaleMap((array) ($screenshot['title'] ?? [])),
            );
        }, $screenshots);
    }

    /**
     * @param  array<int, mixed>  $detailItems
     * @return ProjectEngineeringDetailDto[]
     */
    private function buildEngineeringDetails(array $detailItems): array
    {
        return array_map(function (mixed $item): ProjectEngineeringDetailDto {
            $item = (array) $item;

            return new ProjectEngineeringDetailDto(
                id: isset($item['id']) && is_numeric($item['id'])
                    ? (int) $item['id']
                    : null,
                icon: $item['icon'] ?? null,
                titles: $this->nullableLocaleMap((array) ($item['title'] ?? [])),
                descriptions: $this->nullableLocaleMap((array) ($item['description'] ?? [])),
            );
        }, $detailItems);
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
    private function tagIds(Request $request): array
    {
        return array_values(array_filter(array_map(
            static fn (string $id): int => (int) $id,
            explode(',', (string) $request->query('tags', '')),
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

    private function featuredToggle(int $projectId, bool $featured): JsonResponse
    {
        $this->featuredCase->set($projectId, $featured);

        return response()->json(['data' => self::listItemProps($this->fetchItem($projectId))]);
    }

    private function publicationToggle(int $projectId, bool $published): JsonResponse
    {
        $this->publicationCase->set($projectId, $published);

        return response()->json(['data' => self::listItemProps($this->fetchItem($projectId))]);
    }

    private function fetchItem(int $projectId): ProjectListItemDto
    {
        try {
            return $this->itemCase->get($projectId);
        } catch (ProjectNotFoundException) {
            abort(404);
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

        return response()->json();
    }

    /**
     * The `ProjectTableRowData` wire shape of a list item.
     *
     * @return array<string, mixed>
     */
    private static function listItemProps(ProjectListItemDto $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'preview_image' => $item->preview?->url,
            'is_published' => $item->isPublished,
            'is_featured' => $item->isFeatured,
            'featured_sort_order' => $item->featuredSortOrder,
            'tech_tags_count' => $item->techTagsCount,
            'tech_tags' => array_map(
                static fn (SkillOptionDto $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'icon' => $tag->icon,
                ],
                $item->techTags,
            ),
            'created_at' => $item->createdAt->format('c'),
            'updated_at' => $item->updatedAt->format('c'),
        ];
    }

    /**
     * The `ProjectEditData` wire shape of the stored project data.
     *
     * @return array<string, mixed>
     */
    private static function editProps(ProjectDataDto $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'title' => $project->translations->titles,
            'brief_description' => $project->translations->briefDescriptions,
            'full_description' => $project->translations->fullDescriptions,
            'problem' => $project->translations->problems,
            'solution' => $project->translations->solutions,
            'preview_image' => $project->preview?->url,
            'preview_image_alt' => $project->preview?->altLabels ?? [],
            'github_url' => $project->links->sourceCodeUrl,
            'live_demo_url' => $project->links->liveDemoUrl,
            'is_featured' => $project->isFeatured,
            'featured_sort_order' => $project->featuredSortOrder,
            'is_published' => $project->isPublished,
            'tech_writeup_article_id' => $project->techWriteupArticleId,
            'tech_tags' => $project->techTags,
            'screenshots' => array_map(
                static fn ($screenshot): array => [
                    'id' => $screenshot->id,
                    'image_path' => $screenshot->image->url,
                    'alt_text' => $screenshot->image->altLabels,
                    'title' => $screenshot->titles,
                    'description' => $screenshot->image->descriptions,
                    'sort_order' => $screenshot->sortOrder,
                ],
                $project->screenshots,
            ),
            'detail_items' => array_map(
                static fn ($item): array => [
                    'id' => $item->id,
                    'icon' => $item->icon,
                    'title' => $item->titles,
                    'description' => $item->descriptions,
                ],
                $project->engineeringDetails,
            ),
        ];
    }

    /**
     * The admin list `meta` wire shape from the cursor pagination
     * facts.
     *
     * @return array<string, mixed>
     */
    private function cursorMeta(\App\Features\Shared\App\DTOs\CursorPaginationDto $pagination): array
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
     * variants.
     *
     * @return array<string, mixed>
     */
    private function lengthAwareMeta(\App\Features\Shared\App\DTOs\PaginationDto $pagination): array
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
    private static function cursorProps(?\App\Features\Shared\App\DTOs\CursorDto $cursor): ?array
    {
        return $cursor === null
            ? null
            : [
                'id' => $cursor->id,
                'sort_value' => $cursor->sortValue,
            ];
    }
}
