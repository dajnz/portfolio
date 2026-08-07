<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;

/**
 * Reads the single-row about entity for the admin edit form
 * (admin-section-ui.md:230-239) — all translations included, so the
 * LocaleTabs can render every supported locale.
 */
final class GetAboutForEditUseCase
{
    public function __construct(
        private readonly AboutInfoRepositoryInterface $repository,
    ) {
    }

    public function get(): ?AboutInfo
    {
        return $this->repository->find();
    }
}
