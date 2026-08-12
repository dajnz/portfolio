<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The data the project editor needs (the create/edit form): the stored
 * project data (null when creating), the tech-skill picker options,
 * the published articles of the tech-writeup select and the signed
 * 1-hour preview URL (null on the create form — routes.md:151).
 * Immutable.
 */
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
