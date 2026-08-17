<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Controllers;

use App\Features\Resume\App\UseCases\GetResumeForEditUseCase;
use App\Features\Resume\App\UseCases\RegenerateResumePdfUseCase;
use App\Features\Resume\App\UseCases\UpdateResumeConfigUseCase;
use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\ValueObjects\ResumeMode;
use App\Features\Resume\Infra\Requests\UpdateResumeConfigRequest;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/PUT /admin/resume` (routes.md:134-135, admin-section-ui.md:248-296)
 * and `POST /api/resume/regenerate` (routes.md:194). The edit page props
 * follow the committed resume.yaml contract: `mode`, `layout`,
 * `custom_resume_file`, `about_texts`. When the (seeder-guaranteed)
 * single row is absent the documented defaults render (content-
 * management.md:337-350) — the page is the creation surface.
 */
final class ResumeController
{
    public function __construct(
        private readonly GetResumeForEditUseCase $getCase,
        private readonly UpdateResumeConfigUseCase $updateCase,
        private readonly RegenerateResumePdfUseCase $regenerateCase,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        return $inertia->render('Resume/Admin/Pages/ResumeEditPage', $this->props($this->getCase->get()));
    }

    public function update(UpdateResumeConfigRequest $request): RedirectResponse
    {
        $this->updateCase->update(
            mode: ResumeMode::from((string) $request->input('mode')),
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
    private function props(?ResumeConfig $config): array
    {
        if ($config === null) {
            return [
                'mode' => ResumeMode::Manual->value,
                'layout' => $this->defaultLayout(),
                'custom_resume_file' => null,
                'about_texts' => (object) [],
            ];
        }

        $aboutTexts = [];

        foreach ($this->locales->activeLocales() as $locale) {
            $aboutTexts[$locale] = $config->aboutTextFor($locale) ?? '';
        }

        return [
            'mode' => $config->toMode()->value,
            'layout' => $config->toLayout(),
            'custom_resume_file' => $config->toCustomResumeFile(),
            'about_texts' => $aboutTexts,
        ];
    }

    /**
     * The documented default layout (content-management.md:337-347)
     * normalized to the validated `order` range 0–4 — the example's
     * 1–5 sequence would fail the request's own `between:0,4` rule
     * (B177).
     *
     * @return array<string, mixed>
     */
    private function defaultLayout(): array
    {
        return [
            'template' => 'modern',
            'sections' => [
                'header' => ['visible' => true, 'order' => 0],
                'about_me' => ['visible' => true, 'order' => 1],
                'skills' => ['visible' => true, 'order' => 2],
                'experience' => ['visible' => true, 'order' => 3],
                'contacts' => ['visible' => true, 'order' => 4],
            ],
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
