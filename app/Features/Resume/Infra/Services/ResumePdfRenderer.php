<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Services;

use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The resume PDF renderer (content-management.md:387-401): renders the
 * per-locale resume data through one of the committed Blade templates
 * (`resources/views/resume-templates/{template}.blade.php`) and converts
 * the HTML to PDF via dompdf. The templates receive the raw assembly
 * (`data`) plus the resolved locale; section headings are translated via
 * the `resume.*` catalog keys inside the templates.
 */
final class ResumePdfRenderer implements ResumePdfRendererInterface
{
    public function render(string $template, string $locale, array $data): string
    {
        return Pdf::loadView("resume-templates.{$template}", [
            'data' => $data,
            'locale' => $locale,
        ])->output();
    }
}
