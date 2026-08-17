<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\Infra\Services\ResumePdfCacheInvalidator;
use Illuminate\Support\Facades\Storage;

/**
 * `POST /api/resume/regenerate` (content-management.md:401, routes.md:194)
 * — force-regenerates the resume PDFs: clears every `resume:pdf:*` Redis
 * key (via the shared invalidator) and deletes the generated RustFS files
 * (`about/resume_*.pdf`). The next `/cv` request regenerates from the
 * current data. The manually uploaded file (`custom_resume_file`) is
 * untouched.
 */
final class RegenerateResumePdfUseCase
{
    public function __construct(
        private readonly ResumePdfCacheInvalidator $invalidator,
    ) {
    }

    public function regenerate(): void
    {
        $this->invalidator->clear();

        $disk = Storage::disk('rustfs');

        foreach ($disk->files('about') as $path) {
            if ($this->isGeneratedResumePdf($path)) {
                $disk->delete($path);
            }
        }
    }

    private function isGeneratedResumePdf(string $path): bool
    {
        return str_starts_with(basename($path), 'resume_')
            && str_ends_with($path, '.pdf');
    }
}
