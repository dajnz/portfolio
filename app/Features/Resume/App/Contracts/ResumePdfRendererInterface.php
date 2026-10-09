<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Contracts;

/**
 * Renders a resume PDF from the assembled resume data: a Blade template
 * from `resources/views/resume-templates/` + dompdf. The app layer depends
 * on this contract; the concrete renderer (`Resume/Infra/Services/`) owns
 * the view + dompdf wiring.
 */
interface ResumePdfRendererInterface
{
    /**
     * @param  array<string, mixed>  $data  The per-locale resume assembly.
     */
    public function render(string $template, string $locale, array $data): string;
}
