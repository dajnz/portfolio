<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

final readonly class ProjectEditorDataDto
{
    /**
     * @param  SkillOptionDto[]  $skills
     * @param  ArticleOptionDto[]  $articles
     */
    public function __construct(
        public ?ProjectDataDto $project,
        public array $skills,
        public ?string $previewUrl,
        public array $articles = [],
    ) {
    }
}
