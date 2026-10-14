<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use Tests\Support\Architecture\LayerPurityScanner;

const APP_LAYER_ALLOWED_IMPORT_PATTERN = '/^App\\\\Features\\\\[A-Za-z]+\\\\(App|Domain)\\\\/';

/**
 * The App layer's framework-free surface (architecture.md): it imports only
 * `App/`, `Domain/`, `Shared/Domain/` and `Shared/App/` — never facades,
 * Eloquent/HTTP types, framework helpers or `Infra/` classes. Line numbers are
 * intentionally not part of a baseline entry, so edits above a violation do
 * not churn this list.
 *
 * This is a burn-down list: delete an entry when its file is migrated to the
 * framework-free surface. It must reach empty, at which point the guard is the
 * full mirror of the domain-layer purity guard.
 */
const APP_LAYER_PURITY_BASELINE = [
    'app/Features/Blog/App/UseCases/AdminArticleSearchUseCase.php: non-app-layer import `App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface`',
    'app/Features/Blog/App/UseCases/GetArticleForEditUseCase.php: non-app-layer import `App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface`',
    'app/Features/Blog/App/UseCases/GetArticleListItemUseCase.php: non-app-layer import `App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface`',
    'app/Features/Blog/App/UseCases/ListArticlesUseCase.php: non-app-layer import `App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface`',
    'app/Features/Blog/App/UseCases/ListTagsUseCase.php: non-app-layer import `App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface`',
    'app/Features/Experience/App/UseCases/GetPublicTimelineUseCase.php: non-app-layer import `App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface`',
    'app/Features/Experience/App/UseCases/GetTimelineItemForEditUseCase.php: non-app-layer import `App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: framework type `Illuminate\Database\Eloquent\Builder`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: framework type `Illuminate\Http\Request`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: non-app-layer import `App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Persistence\CursorDecoder`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Persistence\CursorPage`',
    'app/Features/Experience/App/UseCases/ListTimelineItemsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Services\AdminCursorPaginator`',
    'app/Features/Portfolio/App/UseCases/ListContactsUseCase.php: framework type `Illuminate\Http\Request`',
    'app/Features/Portfolio/App/UseCases/ListContactsUseCase.php: non-app-layer import `App\Features\Portfolio\Infra\Repositories\ContactListQueryRepositoryInterface`',
    'app/Features/Portfolio/App/UseCases/ListContactsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Persistence\CursorDecoder`',
    'app/Features/Portfolio/App/UseCases/ListContactsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Persistence\CursorPage`',
    'app/Features/Portfolio/App/UseCases/ListContactsUseCase.php: non-app-layer import `App\Features\Shared\Infra\Services\AdminCursorPaginator`',
    'app/Features/Portfolio/App/UseCases/ListFeedbackUseCase.php: framework type `Illuminate\Http\Request`',
    'app/Features/Portfolio/App/UseCases/ListFeedbackUseCase.php: non-app-layer import `App\Features\Portfolio\Infra\Repositories\FeedbackListQueryRepositoryInterface`',
    'app/Features/Portfolio/App/UseCases/ListFeedbackUseCase.php: non-app-layer import `App\Features\Shared\Infra\Persistence\CursorPage`',
    'app/Features/Portfolio/App/UseCases/ListFeedbackUseCase.php: non-app-layer import `App\Features\Shared\Infra\Services\AdminCursorPaginator`',
    'app/Features/Projects/App/UseCases/AdminProjectSearchUseCase.php: non-app-layer import `App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface`',
    'app/Features/Projects/App/UseCases/GetProjectListItemUseCase.php: non-app-layer import `App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface`',
    'app/Features/Projects/App/UseCases/GetPublicProjectsUseCase.php: non-app-layer import `App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface`',
    'app/Features/Projects/App/UseCases/ListProjectsUseCase.php: non-app-layer import `App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface`',
    'app/Features/Resume/App/UseCases/GetResumeForEditUseCase.php: non-app-layer import `App\Features\Resume\Infra\Repositories\ResumeDataReaderInterface`',
    'app/Features/Resume/App/UseCases/GetResumePdfUseCase.php: non-app-layer import `App\Features\Resume\Infra\Repositories\ResumeDataReaderInterface`',
];

function appLayerForbiddenImport(string $imported): ?string
{
    if (! str_starts_with($imported, 'App\\')) {
        return null;
    }

    if (preg_match(APP_LAYER_ALLOWED_IMPORT_PATTERN, $imported) === 1) {
        return null;
    }

    return "non-app-layer import `{$imported}`";
}

/**
 * Drops the line number from a violation so the baseline is stable across
 * edits.
 */
function appLayerBaselineEntry(string $violation): string
{
    return preg_replace('/ line \d+:/', ':', $violation, 1) ?? $violation;
}

it('keeps the app layer free of Laravel dependencies and infra imports', function (): void {
    $scanner = new LayerPurityScanner(dirname(__DIR__, 4));

    expect($scanner->helperNames())->not->toBeEmpty();

    $violations = $scanner->scan(
        'app/Features/*/App',
        'Tests\Unit\Backend\Architecture\appLayerForbiddenImport',
    );

    $entries = [];

    foreach ($violations as $violation) {
        $entries[appLayerBaselineEntry($violation)] = true;
    }

    $entries = array_keys($entries);
    sort($entries);

    expect($entries)->toBe(APP_LAYER_PURITY_BASELINE);
});
