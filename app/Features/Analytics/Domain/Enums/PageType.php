<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

/**
 * The public page kinds the frontend reports with every analytics
 * event (ADR-0017) — the page context replaces the backend URL matching
 * for the report attribution. `page_entity_id` is only meaningful for
 * the entity pages (`blog_article`, `project_detail`).
 */
enum PageType: string
{
    case Landing = 'landing';

    case BlogIndex = 'blog_index';

    case BlogArticle = 'blog_article';

    case ProjectsIndex = 'projects_index';

    case ProjectDetail = 'project_detail';

    case Experience = 'experience';
}
