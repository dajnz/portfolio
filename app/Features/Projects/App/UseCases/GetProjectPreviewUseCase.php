<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDetailDto;
use App\Features\Projects\App\Services\ProjectDetailDtoBuilder;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;

/**
 * The signed preview page (`/admin/preview/projects/{project}`): the
 * project is resolved by id — trashed and unpublished included — and
 * rendered with the `noindex` SEO variant (the preview must never enter
 * the index). The route signature is validated by the controller; the
 * tech-writeup publication check is shared with the public detail.
 */
final class GetProjectPreviewUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly ProjectDetailDtoBuilder $details,
    ) {
    }

    public function get(int $id, string $locale): ?ProjectDetailDto
    {
        $project = $this->repository->findByIdIncludingTrashed($id);

        return $project === null
            ? null
            : $this->details->buildForPreview($project, $locale);
    }
}
