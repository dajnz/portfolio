<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Infra\Services\ResumeModeReader;

/**
 * Reads the single-row about entity for the admin edit form
 * (admin-section-ui.md:230-239) — all translations included, so the
 * LocaleTabs can render every supported locale — plus the current resume
 * mode for the resume summary line (Resume feature data, read in the
 * Infra layer per ADR-0003).
 *
 * @return array{about: AboutInfo|null, resume_mode: string}
 */
final class GetAboutForEditUseCase
{
    public function __construct(
        private readonly AboutInfoRepositoryInterface $repository,
        private readonly ResumeModeReader $resumeMode,
    ) {
    }

    /**
     * @return array{about: AboutInfo|null, resume_mode: string}
     */
    public function get(): array
    {
        return [
            'about' => $this->repository->find(),
            'resume_mode' => $this->resumeMode->read(),
        ];
    }
}
