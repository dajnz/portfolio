<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;

/**
 * Reads the resume configuration for the admin edit form
 * (admin-section-ui.md:248-296, resume.yaml#ResumeEditPageProps): mode,
 * layout, manual file path and the per-locale about texts. The defaults
 * come from the controller layer when the (seeder-guaranteed) single row
 * is absent — the page itself is the creation surface.
 */
final class GetResumeForEditUseCase
{
    public function __construct(
        private readonly ResumeConfigRepositoryInterface $repository,
    ) {
    }

    public function get(): ?ResumeConfig
    {
        return $this->repository->find();
    }
}
