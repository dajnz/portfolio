<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\DTOs\AdminContactListQueryDto;
use App\Features\Portfolio\App\DTOs\ContactListItemDto;
use App\Features\Portfolio\App\UseCases\DestroyContactUseCase;
use App\Features\Portfolio\App\UseCases\ListContactsUseCase;
use App\Features\Portfolio\App\UseCases\ReorderContactsUseCase;
use App\Features\Portfolio\App\UseCases\StoreContactUseCase;
use App\Features\Portfolio\App\UseCases\UpdateContactUseCase;
use App\Features\Portfolio\Infra\Requests\ReorderContactsRequest;
use App\Features\Portfolio\Infra\Requests\StoreContactRequest;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Contacts admin surface: cursor-paginated reorderable list + CRUD + the
 * `{ids}` reorder. One action → one use case; the list props are mapped
 * from the paginator page here.
 */
final class ContactController
{
    public function __construct(
        private readonly ListContactsUseCase $listCase,
        private readonly StoreContactUseCase $storeCase,
        private readonly UpdateContactUseCase $updateCase,
        private readonly DestroyContactUseCase $destroyCase,
        private readonly ReorderContactsUseCase $reorderCase,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $result = $this->listCase->list($this->buildListQuery($request), App::getLocale());

        return $inertia->render('Portfolio/Admin/Pages/ContactsListPage', [
            'contacts' => $this->items($result->rows),
            'meta' => $this->meta($result->pagination),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $this->storeCase->store(
            value: $request->input('value', ''),
            icon: $request->input('icon'),
            includeInResume: $request->boolean('include_in_resume'),
            url: $request->input('url'),
            sortOrder: (int) $request->input('sort_order', 0),
            names: $request->input('name', []),
        );

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function update(StoreContactRequest $request, int $contact): RedirectResponse
    {
        $this->updateCase->update(
            id: $contact,
            value: $request->input('value', ''),
            icon: $request->input('icon'),
            includeInResume: $request->boolean('include_in_resume'),
            url: $request->input('url'),
            sortOrder: (int) $request->input('sort_order', 0),
            names: $request->input('name', []),
        );

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $this->destroyCase->destroy($contact);

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function reorder(ReorderContactsRequest $request): RedirectResponse
    {
        $this->reorderCase->reorder(array_map('intval', $request->input('ids', [])));

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    /**
     * The app-layer admin list query — the raw query values are
     * extracted here, the paginator parsing happens in the infra
     * paginator implementation.
     */
    private function buildListQuery(Request $request): AdminContactListQueryDto
    {
        return new AdminContactListQueryDto(
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
     * @param  ContactListItemDto[]  $rows
     * @return array<int, array<string, mixed>>
     */
    private function items(array $rows): array
    {
        return array_map(
            static fn (ContactListItemDto $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'names' => $row->names,
                'value' => $row->value,
                'icon' => $row->icon,
                'url' => $row->url,
                'include_in_resume' => $row->includeInResume,
                'sort_order' => $row->sortOrder,
            ],
            $rows,
        );
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
}
