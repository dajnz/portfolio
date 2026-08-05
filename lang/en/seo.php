<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO titles and descriptions (seo.md)
|--------------------------------------------------------------------------
|
| Page titles, Open Graph text and JSON-LD string values resolved via
| `__('seo.*')` (seo.md:33-45,84). `:name`/`:project_title`/`:article_title`
| are Laravel replacement params.
|
*/

return [
    'title_landing' => ':name — Full-Stack Developer',
    'description_landing' => 'Full-stack developer portfolio with integrated blog, built with Laravel and Vue 3.',

    'title_projects_index' => 'Projects — :name',
    'description_projects_index' => 'Projects and case studies by :name.',

    'title_project_detail' => ':project_title — :name',

    'title_blog_index' => 'Blog — :name',
    'description_blog_index' => 'Articles on web development, architecture and tooling by :name.',

    'title_blog_article' => ':article_title — :name',

    'title_experience' => 'Experience — :name',
    'description_experience' => 'Professional experience and career timeline.',

    'jsonld_person_job_title' => 'Full-Stack Web Developer',
];
