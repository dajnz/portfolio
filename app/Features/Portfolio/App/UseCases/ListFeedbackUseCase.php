<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\Infra\Persistence\CursorPage;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use Illuminate\Http\Request;

/**
 * The admin feedback list (admin-section-ui.md:404-408): cursor-paginated
 * via the shared paginator (step 2.8), newest first (default id desc).
 */
final class ListFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
        private readonly SettingRepositoryInterface $settings,
        private readonly AdminCursorPaginator $paginator = new AdminCursorPaginator(),
    ) {
    }

    public function list(Request $request): CursorPage
    {
        return $this->paginator->paginate(
            $this->repository->listQuery(),
            $request,
            $this->settings->value(SettingName::ItemsPerPage, config('portfolio.items_per_page', 12)),
            'feedback',
        );
    }
}
