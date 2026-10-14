<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Services;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Exceptions\AdminNotFoundException;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;

/**
 * Resolves an admin entity for the flows that act on the authenticated
 * account — the controllers pass the id, never the entity.
 */
final class AdminResolver
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
    ) {
    }

    public function require(int $adminId): Admin
    {
        return $this->repository->findById($adminId) ?? throw AdminNotFoundException::forId($adminId);
    }
}
