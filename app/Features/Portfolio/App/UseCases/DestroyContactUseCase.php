<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;

/**
 * Deletes a contact (translations cascade via FK).
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
        AdminCursorPaginator::invalidateCountCache('contacts');
    }
}
