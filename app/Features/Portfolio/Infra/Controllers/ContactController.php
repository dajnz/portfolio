<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\DestroyContactUseCase;
use App\Features\Portfolio\App\UseCases\ListContactsUseCase;
use App\Features\Portfolio\App\UseCases\ReorderContactsUseCase;
use App\Features\Portfolio\App\UseCases\StoreContactUseCase;
use App\Features\Portfolio\App\UseCases\UpdateContactUseCase;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Requests\ReorderContactsRequest;
use App\Features\Portfolio\Infra\Requests\StoreContactRequest;
use App\Features\Shared\Infra\Persistence\CursorPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Contacts admin surface (routes.md:76-80, admin-section-ui.md:304-308):
 * cursor-paginated reorderable list + CRUD + the A4 `{ids}` reorder.
 * One action → one use case; the list props are mapped from the
 * paginator page here.
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
        $page = $this->listCase->list($request);

        return $inertia->render('Portfolio/Admin/Pages/ContactsListPage', [
            'contacts' => $this->items($page),
            'meta' => $this->meta($page),
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

        return redirect()->back()
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

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $this->destroyCase->destroy($contact);

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function reorder(ReorderContactsRequest $request): RedirectResponse
    {
        $this->reorderCase->reorder(array_map('intval', $request->input('ids', [])));

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(CursorPage $page): array
    {
        $locale = app()->getLocale();

        return array_map(
            fn (ContactModel $model): array => [
            'id' => $model->id,
            'name' => $this->nameFor($model, $locale),
            // Per-locale names map — the inline include-in-resume toggle
            // PUTs the full map back (StoreContactRequest requires every
            // active locale's name, content-management.md:609).
            'names' => $model->translations->pluck('name', 'locale')->all(),
            'value' => $model->value,
            'icon' => $model->icon,
            'url' => $model->url,
            'include_in_resume' => $model->include_in_resume,
            'sort_order' => $model->sort_order,
            ],
            $page->items,
        );
    }

    private function nameFor(ContactModel $model, string $locale): string
    {
        $rows = $model->translations->keyBy('locale');

        $name = $rows->get($locale)?->name ?? $rows->get('en')?->name;

        return $name ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(CursorPage $page): array
    {
        return [
            'total' => $page->total,
            'per_page' => $page->perPage,
            'current_page' => $page->currentPage,
            'sort_column' => $page->sorting->sortColumn,
            'sort_direction' => $page->sorting->sortDirection,
            'cursors' => $page->cursors->toArray(),
        ];
    }
}
