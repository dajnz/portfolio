<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\Infra\Persistence\CursorDecoder;
use App\Features\Shared\Infra\Persistence\CursorPage;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The admin experience list (admin-section-ui.md:355-359): cursor-
 * paginated via the shared paginator (step 2.8), sortable by date
 * (`?sort_column=started_at`, forward/reverse — `id` stays the
 * fallback), searchable by content (ILIKE over the per-locale
 * position/company/short-description — no FTS at admin list scale) and
 * toggled to the trash via `?trashed=1` (only soft-deleted items).
 */
final class ListTimelineItemsUseCase
{
    private readonly AdminCursorPaginator $paginator;

    public function __construct(
        private readonly TimelineItemListQueryRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        CursorDecoder $cursorDecoder = new CursorDecoder(),
    ) {
        $this->paginator = new AdminCursorPaginator(
            cursorDecoder: $cursorDecoder,
            allowedSortColumns: ['started_at'],
        );
    }

    public function list(Request $request): CursorPage
    {
        $query = $this->repository->listQuery();

        $trashed = $request->boolean('trashed');
        $this->applyTrashedFilter($query, $trashed);
        $search = $this->applySearch($query, $request);

        return $this->paginator->paginate(
            $query,
            $request,
            (int) $this->settings->value(SettingName::ItemsPerPage, config('portfolio.items_per_page', 12)),
            $this->countCacheKey($trashed, $search),
        );
    }

    /**
     * The 30 s count cache is per filter combination (B72) — the
     * trashed toggle and the search term both change the counted set.
     */
    private function countCacheKey(bool $trashed, string $search): string
    {
        return 'experience:'.($trashed ? 'trashed' : 'live').':q='.md5($search);
    }

    /**
     * @param  Builder<TimelineItem>  $query
     */
    private function applyTrashedFilter(Builder $query, bool $trashed): void
    {
        if ($trashed) {
            $query->onlyTrashed();

            return;
        }

        $query->whereNull('deleted_at');
    }

    /**
     * Content search — ILIKE over the per-locale text columns
     * (content-management.md:185). Returns the normalized search term
     * for the count-cache key.
     *
     * @param  Builder<TimelineItem>  $query
     */
    private function applySearch(Builder $query, Request $request): string
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return '';
        }

        $query->whereHas('translations', function (Builder $translations) use ($search): void {
            $translations->where(function (Builder $columns) use ($search): void {
                $columns->where('position', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%")
                    ->orWhere('company_country', 'ilike', "%{$search}%")
                    ->orWhere('short_description', 'ilike', "%{$search}%");
            });
        });

        return $search;
    }
}
