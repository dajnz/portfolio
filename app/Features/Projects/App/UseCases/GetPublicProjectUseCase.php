<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDetailDto;
use App\Features\Projects\App\Services\ProjectDetailDtoBuilder;
use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;

/**
 * The public project detail (public-section-ui.md:177-207): the full
 * aggregate resolved by slug, mapped to the data/SEO DTOs with the
 * tech-writeup link gated by the article's CURRENT publication state
 * (content-management.md:133). Old-slug 301 resolution (B5/B6) lives
 * here too (`currentSlugFor()`).
 */
final class GetPublicProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly ProjectDetailDtoBuilder $details,
    ) {
    }

    public function get(string $slug, bool $includeUnpublished, string $locale): ?ProjectDetailDto
    {
        $project = $includeUnpublished
            ? $this->repository->findBySlugIncludingUnpublished($slug)
            : $this->repository->findBySlug($slug);

        return $this->toDetailDto($project, $locale);
    }

    /**
     * The current slug mapped from an old-slug record — the 301 target
     * of the public detail controller (B5/B6, content-management.md:
     * 454-464). Null when the slug is not recorded (a trashed project's
     * history resolves to nothing).
     */
    public function currentSlugFor(string $slug): ?string
    {
        return $this->repository->currentSlugForOld($slug);
    }

    private function toDetailDto(?Project $project, string $locale): ?ProjectDetailDto
    {
        return $project === null
            ? null
            : $this->details->build($project, $locale);
    }
}
