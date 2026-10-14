<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Services;

use App\Features\Projects\App\DTOs\ProjectDataDto;
use App\Features\Projects\App\DTOs\ProjectSeoDto;
use App\Features\Shared\App\Contracts\ApplicationUrlInterface;
use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;

/**
 * Builds the project pages' SEO DTOs (public and signed-preview variants) —
 * resolves the site name, the localized titles and the absolute URLs, so the
 * public project use cases only supply the page data.
 */
final class ProjectSeoFactory
{
    public function __construct(
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly TranslatorInterface $translator,
        private readonly ApplicationUrlInterface $applicationUrl,
    ) {
    }

    public function forIndex(string $locale): ProjectSeoDto
    {
        $siteName = $this->about->findSiteName($locale);

        return ProjectSeoDto::forIndex(
            title: $this->translator->translate('seo.title_projects_index', ['name' => $siteName]),
            description: $this->translator->translate('seo.description_projects_index', ['name' => $siteName]),
            baseUrl: $this->applicationUrl->baseUrl(),
        );
    }

    public function forDetail(ProjectDataDto $project, string $locale): ProjectSeoDto
    {
        return ProjectSeoDto::forDetail(
            project: $project,
            locale: $locale,
            baseUrl: $this->applicationUrl->baseUrl(),
            seoTitle: $this->title($project, $locale, $this->about->findSiteName($locale)),
            projectsLabel: $this->translator->translate('nav.projects'),
        );
    }

    /**
     * The signed preview: the same title/description with the `noindex`
     * robots directive.
     */
    public function forPreview(ProjectDataDto $project, string $locale): ProjectSeoDto
    {
        return ProjectSeoDto::forPreview(
            project: $project,
            locale: $locale,
            baseUrl: $this->applicationUrl->baseUrl(),
            seoTitle: $this->title($project, $locale, ''),
        );
    }

    private function title(ProjectDataDto $project, string $locale, string $siteName): string
    {
        return $this->translator->translate('seo.title_project_detail', [
            'project_title' => ProjectSeoDto::localeText($project->translations->titles, $locale),
            'name' => $siteName,
        ]);
    }
}
