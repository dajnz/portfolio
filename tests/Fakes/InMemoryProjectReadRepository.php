<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;

final class InMemoryProjectReadRepository implements ProjectReadRepositoryInterface
{
    /**
     * @param  FeaturedProject[]  $featured
     */
    public function __construct(
        private readonly array $featured = [],
        private readonly int $publishedCount = 0,
    ) {
    }

    /**
     * @return FeaturedProject[]
     */
    public function findFeatured(string $locale, int $limit = 5): array
    {
        return array_slice($this->featured, 0, $limit);
    }

    public function countPublished(): int
    {
        return $this->publishedCount;
    }
}
