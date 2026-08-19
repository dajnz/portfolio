<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Controllers;

use App\Features\Resume\App\DTOs\ResumeEditDataDto;
use App\Features\Resume\App\UseCases\GetResumeForEditUseCase;
use App\Features\Resume\App\UseCases\RegenerateResumePdfUseCase;
use App\Features\Resume\App\UseCases\UpdateResumeConfigUseCase;
use App\Features\Resume\Infra\Requests\UpdateResumeConfigRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/PUT /admin/resume` (routes.md:134-135, admin-section-ui.md:248-296)
 * and `POST /api/resume/regenerate` (routes.md:194). The controller only
 * wires requests to use cases and maps their results to the page props
 * (resume.yaml contract: `mode`, `layout`, `custom_resume_file`,
 * `about_texts`) — the defaults for an absent config row come from the
 * use case.
 */
final class ResumeController
{
    public function __construct(
        private readonly GetResumeForEditUseCase $getCase,
        private readonly UpdateResumeConfigUseCase $updateCase,
        private readonly RegenerateResumePdfUseCase $regenerateCase,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        return $inertia->render('Resume/Admin/Pages/ResumeEditPage', $this->props($this->getCase->get()));
    }

    public function update(UpdateResumeConfigRequest $request): RedirectResponse
    {
        $this->updateCase->update(
            mode: (string) $request->input('mode'),
            layout: (array) $request->input('layout'),
            customResumeFile: $request->input('custom_resume_file') ?: null,
            aboutTexts: $this->aboutTexts($request),
        );

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function regenerate(): JsonResponse
    {
        $this->regenerateCase->regenerate();

        return response()->json([
            'data' => ['message' => __('admin.resume.regenerated')],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(ResumeEditDataDto $dto): array
    {
        return [
            'mode' => $dto->mode,
            'layout' => $dto->layout,
            'custom_resume_file' => $dto->customResumeFile,
            'about_texts' => $dto->aboutTexts,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function aboutTexts(UpdateResumeConfigRequest $request): array
    {
        $texts = [];

        foreach ((array) $request->input('about_text', []) as $locale => $text) {
            $texts[(string) $locale] = is_string($text) ? $text : null;
        }

        return $texts;
    }
}
