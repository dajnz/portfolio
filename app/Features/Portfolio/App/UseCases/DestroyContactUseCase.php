<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;

/**
 * Deletes a contact (translations cascade via FK — database.md:182-189).
 */
final class DestroyContactUseCase
{
    public function __construct(
        private readonly ContactRepositoryInterface $repository,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);
    }
}
