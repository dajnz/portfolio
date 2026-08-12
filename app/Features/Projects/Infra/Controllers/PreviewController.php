<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Controllers;

use App\Features\Projects\App\UseCases\GetProjectPreviewUseCase;
use App\Features\Projects\Infra\Services\ProjectDetailPropsBuilder;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /admin/preview/projects/{project}` (routes.md:156,60) — the
 * signed preview URL: 1-hour expiry via `URL::temporarySignedRoute()`,
 * no `auth` middleware. Renders the public project detail page while
 * bypassing the `is_published` scope, so the admin can preview a draft
 * (routes.md:60) — the page is resolved by the preview use case (the
 * tech-writeup publication check and the `noindex` SEO variant are its
 * concern).
 */
final class PreviewController
{
    public function __construct(
        private readonly GetProjectPreviewUseCase $previewCase,
        private readonly ProjectDetailPropsBuilder $props,
    ) {
    }

    public function project(ResponseFactory $inertia, Request $request, int $project): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $detail = $this->previewCase->get($project, app()->getLocale());

        if ($detail === null) {
            abort(404);
        }

        return $inertia->render('Projects/Public/Pages/ProjectDetailPage', [
            'project' => $this->props->detail($detail->project, $detail->techWriteup, app()->getLocale()),
            'seo' => $this->props->seo($detail->seo),
        ]);
    }
}
