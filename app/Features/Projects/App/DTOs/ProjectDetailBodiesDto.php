<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The pipeline-rendered markdown bodies of the public project detail
 * (content-management.md:131, ADR-0008): `full_description`, `problem` and
 * `solution` resolved for the request locale (with the `en` fallback)
 * and rendered to safe HTML by the shared content pipeline. Null when
 * the locale has no content. The controller maps these onto the wire
 * — the frontend injects them as-is (v-html). Immutable.
 */
final readonly class ProjectDetailBodiesDto
{
    public function __construct(
        public ?string $fullDescription,
        public ?string $problem,
        public ?string $solution,
    ) {
    }
}
