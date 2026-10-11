<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;

/**
 * The admin list publish/unpublish quick action: flips an item's
 * `is_published` flag.
 */
final class UpdateTimelinePublicationUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
    ) {
    }

    public function set(int $id, bool $published): void
    {
        $this->repository->setPublished($id, $published);
    }
}
