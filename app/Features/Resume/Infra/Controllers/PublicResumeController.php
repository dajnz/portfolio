<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Controllers;

use App\Features\Resume\App\UseCases\GetResumePdfUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /cv` and `GET /{locale}/cv` — serves the resume PDF directly (no
 * Inertia page): the manual upload in `manual` mode, the generated
 * `about/resume_{locale}_{hash}.pdf` in `auto` mode. The `?locale=` query
 * parameter overrides the locale cookie for this request only (the admin
 * "Download preview" button depends on it); the generated PDF's content
 * is rendered in that locale.
 */
final class PublicResumeController
{
    public function __construct(
        private readonly GetResumePdfUseCase $pdfCase,
    ) {
    }

    public function download(Request $request): StreamedResponse
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);

        $pdf = $this->pdfCase->get($locale);

        if ($pdf === null) {
            abort(404);
        }

        return Storage::disk('rustfs')->response(
            $pdf->path,
            $pdf->fileName,
            [
                'Content-Type' => $pdf->contentType,
                'Content-Disposition' => 'inline; filename="'.$pdf->fileName.'"',
            ],
        );
    }

    /**
     * The `?locale=` parameter wins when it names a supported locale;
     * otherwise the middleware-resolved locale (cookie → preferred
     * language → default) stands.
     */
    private function resolveLocale(Request $request): string
    {
        $queryLocale = $request->query('locale');

        if (is_string($queryLocale)
            && in_array($queryLocale, config('portfolio.supported_locales', ['en', 'ru']), true)
        ) {
            return $queryLocale;
        }

        return (string) App::getLocale();
    }
}
