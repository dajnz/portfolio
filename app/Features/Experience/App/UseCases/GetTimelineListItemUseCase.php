<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\App\DTOs\TimelineItemListItemDto;
use App\Features\Experience\Domain\Exceptions\TimelineItemNotFoundException;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;

/**
 * The single admin list item after a quick action (publish/unpublish):
 * the mutation runs through its own use case, this one re-reads the
 * aggregate so the list updates without a reload.
 */
final class GetTimelineListItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
    ) {
    }

    public function get(int $id, string $locale): TimelineItemListItemDto
    {
        $item = $this->repository->findById($id);

        if ($item === null) {
            throw TimelineItemNotFoundException::forId($id);
        }

        return TimelineItemListItemDto::fromEntity($item, $locale);
    }
}
