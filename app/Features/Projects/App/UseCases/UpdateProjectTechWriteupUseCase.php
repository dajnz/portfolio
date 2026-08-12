<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\BlogArticleId;

/**
 * Links/unlinks the tech-writeup article (content-management.md:159):
 * the article must be published at save time (validated by the
 * request); the public controller re-checks `is_published` at display
 * time so an unpublished article hides the link without breaking the
 * stored reference.
 */
final class UpdateProjectTechWriteupUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
    ) {
    }

    public function update(int $id, ?BlogArticleId $techWriteupArticleId): void
    {
        $this->repository->setTechWriteup($id, $techWriteupArticleId?->toValue());
    }
}
