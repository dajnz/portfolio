<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Controllers;

use App\Features\Experience\App\DTOs\AdminTimelineListQueryDto;
use App\Features\Experience\App\DTOs\TimelineItemListItemDto;
use App\Features\Experience\App\UseCases\DestroyTimelineItemUseCase;
use App\Features\Experience\App\UseCases\ForceDestroyTimelineItemUseCase;
use App\Features\Experience\App\UseCases\GetTimelineItemForEditUseCase;
use App\Features\Experience\App\UseCases\GetTimelineListItemUseCase;
use App\Features\Experience\App\UseCases\ListTimelineItemsUseCase;
use App\Features\Experience\App\UseCases\RestoreTimelineItemUseCase;
use App\Features\Experience\App\UseCases\StoreTimelineItemUseCase;
use App\Features\Experience\App\UseCases\UpdateTimelineItemUseCase;
use App\Features\Experience\App\UseCases\UpdateTimelinePublicationUseCase;
use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Exceptions\TimelineItemNotFoundException;
use App\Features\Experience\Domain\Exceptions\TimelinePeriodConflictException;
use App\Features\Experience\Infra\Requests\StoreExperienceRequest;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Infra\Services\PerLocaleValues;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Experience admin surface: the cursor-paginated list with date sort /
 * content search / trashed view, the create/edit forms (per-locale maps
 * for the translatable fields — the LocaleTabs convention, `data-dynamic`
 * keys), and the CRUD + soft-delete lifecycle. One action → one use case;
 * the props are mapped from the hydrated entities here (the per-locale
 * maps come straight from the entity's locale projections).
 */
final class AdminExperienceController
{
    public function __construct(
        private readonly ListTimelineItemsUseCase $listCase,
        private readonly GetTimelineItemForEditUseCase $editCase,
        private readonly StoreTimelineItemUseCase $storeCase,
        private readonly UpdateTimelineItemUseCase $updateCase,
        private readonly DestroyTimelineItemUseCase $destroyCase,
        private readonly RestoreTimelineItemUseCase $restoreCase,
        private readonly ForceDestroyTimelineItemUseCase $forceDestroyCase,
        private readonly UpdateTimelinePublicationUseCase $publicationCase,
        private readonly GetTimelineListItemUseCase $itemCase,
        private readonly PerLocaleValues $localeValues,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $result = $this->listCase->list($this->buildListQuery($request));

        return $inertia->render('Experience/Admin/Pages/ExperienceListPage', [
            'items' => $this->tableRows($result->rows),
            'meta' => $this->meta($result->pagination),
        ]);
    }

    public function create(ResponseFactory $inertia): Response
    {
        return $this->formPage($inertia, null);
    }

    public function store(StoreExperienceRequest $request): RedirectResponse
    {
        try {
            $itemId = $this->storeCase->store(...$this->payload($request));
        } catch (TimelinePeriodConflictException $e) {
            throw ValidationException::withMessages([$e->toField() => $e->getMessage()]);
        }

        // Redirect to the edit page of the created item: staying on the
        // create form would keep the client in create mode (Inertia
        // preserves the component across the POST redirect), so the next
        // save would insert another item instead of updating.
        return Redirect::to("/admin/experience/{$itemId}/edit")
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function edit(ResponseFactory $inertia, int $item): Response
    {
        return $this->formPage($inertia, $item);
    }

    public function update(StoreExperienceRequest $request, int $item): RedirectResponse
    {
        try {
            $this->updateCase->update($item, ...$this->payload($request));
        } catch (TimelinePeriodConflictException $e) {
            throw ValidationException::withMessages([$e->toField() => $e->getMessage()]);
        }

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $item): RedirectResponse
    {
        $this->destroyCase->destroy($item);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function restore(Request $request, int $item): RedirectResponse
    {
        $this->restoreCase->restore($item);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.restored')]);
    }

    public function forceDestroy(Request $request, int $item): RedirectResponse
    {
        $this->forceDestroyCase->forceDestroy($item);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function publish(Request $request, int $item): JsonResponse
    {
        return $this->publicationToggle($item, true);
    }

    public function unpublish(Request $request, int $item): JsonResponse
    {
        return $this->publicationToggle($item, false);
    }

    private function formPage(ResponseFactory $inertia, ?int $itemId): Response
    {
        $locale = App::getLocale();
        $data = $this->editCase->get($itemId, $locale);

        /** @var TimelineItem|null $item */
        $item = $data['item'];

        return $inertia->render('Experience/Admin/Pages/ExperienceFormPage', [
            'item' => $item === null ? null : $this->editProps($item),
            'skills' => $this->skillProps($data['skills']),
            'projects' => $data['projects'],
        ]);
    }

    /**
     * The app-layer admin list query — the raw query values are
     * extracted here, the paginator parsing happens in the infra
     * paginator implementation.
     */
    private function buildListQuery(Request $request): AdminTimelineListQueryDto
    {
        return new AdminTimelineListQueryDto(
            trashed: $request->boolean('trashed'),
            search: trim((string) $request->query('q', '')),
            page: max(1, (int) $request->query('page', 1)),
            cursorParams: is_array($request->input('cursor')) ? $request->input('cursor') : null,
            sortColumn: $this->sortColumn($request),
            sortDirection: $this->sortDirection($request),
            url: $request->fullUrl(),
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
     * Spread-ready payload for the store/update use cases — extracted so
     * both actions share one input mapping. An empty `ended_at` is the
     * ongoing marker; the continuity rules live in the use cases.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreExperienceRequest $request): array
    {
        $endedAt = $request->input('ended_at');

        return [
            'position' => $this->localeValues->inputMap((array) $request->input('position', [])),
            'companyName' => $this->localeValues->inputMap((array) $request->input('company_name', [])),
            'companyCountry' => $this->localeValues->inputMap((array) $request->input('company_country', [])),
            'shortDescription' => $this->localeValues->inputMap((array) $request->input('short_description', [])),
            'achievements' => (array) $request->input('achievements', []),
            'techTagIds' => array_map('intval', (array) $request->input('tech_tags', [])),
            'startedAt' => new DateTimeImmutable((string) $request->input('started_at')),
            'endedAt' => is_string($endedAt) && $endedAt !== ''
                ? new DateTimeImmutable($endedAt)
                : null,
            'isPublished' => $request->boolean('is_published'),
            'sortOrder' => (int) $request->input('sort_order', 0),
            'projectId' => $request->input('project_id') !== null
                ? (int) $request->input('project_id')
                : null,
            'includeInResume' => $request->boolean('include_in_resume'),
            'externalProjectName' => $request->input('external_project_name'),
        ];
    }

    private function publicationToggle(int $itemId, bool $published): JsonResponse
    {
        $this->publicationCase->set($itemId, $published);

        return ResponseFacade::json(['data' => self::listItemProps($this->fetchItem($itemId))]);
    }

    private function fetchItem(int $itemId): TimelineItemListItemDto
    {
        try {
            return $this->itemCase->get($itemId, App::getLocale());
        } catch (TimelineItemNotFoundException) {
            abort(404);
        }
    }

    /**
     * The `TimelineItemTableRowData` wire shape of a list item.
     *
     * @return array<string, mixed>
     */
    private static function listItemProps(TimelineItemListItemDto $item): array
    {
        return [
            'id' => $item->id,
            'position' => $item->position,
            'company_name' => $item->companyName,
            'started_at' => $item->startedAt->format('Y-m-d'),
            'ended_at' => $item->endedAt?->format('Y-m-d'),
            'is_published' => $item->isPublished,
            'include_in_resume' => $item->includeInResume,
        ];
    }

    /**
     * @param  array<int, mixed>  $models  Raw rows from the paginator page.
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(array $models): array
    {
        $locale = App::getLocale();

        return array_map(function (mixed $model) use ($locale): array {
            $rows = $model->translations->keyBy('locale');
            $row = $rows->get($locale) ?? $rows->get('en');

            return [
                'id' => $model->id,
                'position' => $row?->position ?? '',
                'company_name' => $row?->company_name ?? '',
                'started_at' => $model->started_at->format('Y-m-d'),
                'ended_at' => $model->ended_at?->format('Y-m-d'),
                'is_published' => (bool) $model->is_published,
                'include_in_resume' => (bool) $model->include_in_resume,
                'created_at' => $model->created_at->toIso8601String(),
            ];
        }, $models);
    }

    /**
     * The admin list `meta` wire shape from the cursor pagination
     * facts.
     *
     * @return array<string, mixed>
     */
    private function meta(CursorPaginationDto $pagination): array
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

    /**
     * @return array<string, mixed>
     */
    private function editProps(TimelineItem $item): array
    {
        $period = $item->toPeriod();

        return [
            'id' => $item->toId(),
            'position' => $item->positionsByLocale(),
            'company_name' => $item->companyNamesByLocale(),
            'company_country' => $item->companyCountriesByLocale(),
            'short_description' => $item->shortDescriptionsByLocale(),
            'project_id' => $item->toRelatedProject()?->toOwnProjectId(),
            'external_project_name' => $item->toRelatedProject()?->toExternalProjectName(),
            'started_at' => $period->toStartedAt()->format('Y-m-d'),
            'ended_at' => $period->toEndedAt()?->format('Y-m-d'),
            'is_published' => $item->toIsPublished(),
            'sort_order' => $item->toSortOrder()->toValue(),
            'include_in_resume' => $item->toIncludeInResume(),
            'achievements' => $this->achievementsProps($item),
            'tech_tags' => $this->techTagProps($item->toTechTags()),
        ];
    }

    /**
     * The achievements of all locales merged by index into the unified
     * form list — each entry carries the per-locale description map so
     * the LocaleTabs-bound editor can round-trip every translation
     * (an achievement may describe itself in one language only). The
     * per-locale maps come from the entity's own projections; only the
     * index merge happens here.
     *
     * @return array<int, array{description: array<string, string|null>, project_link: string|null}>
     */
    private function achievementsProps(TimelineItem $item): array
    {
        $descriptions = $item->achievementDescriptionsByLocale();
        $projectLinks = $item->achievementProjectLinksByLocale();
        $locales = array_keys($descriptions);
        $maxCount = $descriptions === [] ? 0 : max(array_map('count', $descriptions));

        $merged = [];
        for ($index = 0; $index < $maxCount; $index++) {
            $descriptionMap = [];
            $projectLink = null;

            foreach ($locales as $locale) {
                $descriptionMap[$locale] = $descriptions[$locale][$index] ?? null;

                if ($projectLink === null) {
                    $projectLink = $projectLinks[$locale][$index] ?? null;
                }
            }

            $merged[] = [
                'description' => $descriptionMap,
                'project_link' => $projectLink,
            ];
        }

        return $merged;
    }

    /**
     * @param  TechSkill[]  $skills  The picker options.
     * @return array<int, array<string, mixed>>
     */
    private function skillProps(array $skills): array
    {
        return array_map(static fn (TechSkill $skill): array => [
            'id' => $skill->toId(),
            'name' => $skill->toName(),
            'icon' => $skill->toIcon(),
        ], $skills);
    }

    /**
     * @param  TechSkillTag[]  $tags  The item's attached tech tags.
     * @return array<int, array<string, mixed>>
     */
    private function techTagProps(array $tags): array
    {
        return array_map(static fn (TechSkillTag $tag): array => [
            'id' => $tag->toId(),
            'name' => $tag->toSkillName(),
            'icon' => $tag->toIcon(),
        ], $tags);
    }
}
