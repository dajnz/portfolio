<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

final readonly class ProjectDetailDto
{
    public function __construct(
        public ProjectDataDto $project,
        public ProjectDetailBodiesDto $bodies,
        public ?TechWriteupLinkDto $techWriteup,
        public ProjectSeoDto $seo,
    ) {
    }
}
