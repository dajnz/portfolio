<?php

declare(strict_types=1);

namespace App\Features\Resume\App\DTOs;

/**
 * The result of the resume PDF lookup/generation
 * (content-management.md:392-399): the RustFS path of the PDF to serve
 * (either the generated `about/resume_{locale}_{hash}.pdf` or the
 * manually uploaded file) plus the download presentation.
 */
final readonly class ResumePdfDto
{
    public function __construct(
        public string $path,
        public string $fileName,
        public string $contentType,
    ) {
    }
}
