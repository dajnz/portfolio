<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The public project detail (public-section-ui.md:177-207): the
 * project's data, the pipeline-rendered markdown bodies for the
 * request locale (content-management.md:131, B23), the tech-writeup
 * link (resolved against the article's CURRENT publication state —
 * content-management.md:133) and the SEO props. Returned by the
 * detail/preview use cases; the controller prepares its own output
 * shape from it. Immutable.
 */
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
