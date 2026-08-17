<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;

/**
 * Records the render calls instead of producing a real PDF — the Resume
 * tests assert the pipeline flow (cache hit/miss, locale, data assembly)
 * without dompdf.
 */
final class FakeResumePdfRenderer implements ResumePdfRendererInterface
{
    /**
     * @var array<int, array{template: string, locale: string, data: array<string, mixed>}>
     */
    public array $rendered = [];

    public function render(string $template, string $locale, array $data): string
    {
        $this->rendered[] = [
            'template' => $template,
            'locale' => $locale,
            'data' => $data,
        ];

        return '%PDF-1.7 fake-resume';
    }

    public function reset(): void
    {
        $this->rendered = [];
    }
}
