<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\App\Contracts\ResumePdfCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\AssetStorageInterface;

/**
 * `POST /api/resume/regenerate` — force-regenerates the resume PDFs:
 * clears every cached resume PDF path (via the shared invalidator) and
 * deletes the generated RustFS files (`about/resume_*.pdf`). The next
 * `/cv` request regenerates from the current data. The manually uploaded
 * file (`custom_resume_file`) is untouched.
 */
final class RegenerateResumePdfUseCase
{
    public function __construct(
        private readonly ResumePdfCacheInvalidatorInterface $invalidator,
        private readonly AssetStorageInterface $storage,
    ) {
    }

    public function regenerate(): void
    {
        $this->invalidator->clear();

        $paths = [];

        foreach ($this->storage->files('about') as $path) {
            if ($this->isGeneratedResumePdf($path)) {
                $paths[] = $path;
            }
        }

        $this->storage->delete($paths);
    }

    private function isGeneratedResumePdf(string $path): bool
    {
        return str_starts_with(basename($path), 'resume_')
            && str_ends_with($path, '.pdf');
    }
}
