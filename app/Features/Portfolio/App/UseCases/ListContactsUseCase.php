<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Infra\Repositories\ContactListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\Infra\Persistence\CursorDecoder;
use App\Features\Shared\Infra\Persistence\CursorPage;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use Illuminate\Http\Request;

/**
 * The admin contacts list: cursor-paginated via the shared paginator. The
 * reorderable list sorts by `sort_order` (asc) — the frontend requests it
 * via the `?sort_column=`/`?sort_direction=` params; `id` stays the
 * fallback.
 */
final class ListContactsUseCase
{
    private readonly AdminCursorPaginator $paginator;

    public function __construct(
        private readonly ContactListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly PaginationDefaultsInterface $paginationDefaults,
        CursorDecoder $cursorDecoder = new CursorDecoder(),
    ) {
        $this->paginator = new AdminCursorPaginator(
            cursorDecoder: $cursorDecoder,
            allowedSortColumns: ['sort_order'],
        );
    }

    public function list(Request $request): CursorPage
    {
        return $this->paginator->paginate(
            $this->repository->listQuery(),
            $request,
            $this->settings->value(SettingName::ItemsPerPage, $this->paginationDefaults->itemsPerPage()),
            'contacts',
        );
    }
}
