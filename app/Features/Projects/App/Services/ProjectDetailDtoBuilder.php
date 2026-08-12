<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Services;

use App\Features\Projects\App\DTOs\ProjectDataDto;
use App\Features\Projects\App\DTOs\ProjectDetailDto;
use App\Features\Projects\App\DTOs\ProjectSeoDto;
use App\Features\Projects\App\DTOs\TechWriteupLinkDto;
use App\Features\Projects\Domain\Entities\Project;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\PublishedArticleReadRepositoryInterface;

/**
 * Builds the {@see ProjectDetailDto} of an aggregate — the project's
 * data DTO, the tech-writeup reference resolved against the CURRENT
 * publication state (content-management.md:133; the stored reference
 * is preserved so the link reappears on re-publish) and the SEO data
 * (the public variant with the site name, the preview variant with the
 * `noindex` directive). Shared by the public detail and the signed
 * preview use cases.
 */
final class ProjectDetailDtoBuilder
{
    public function __construct(
        private readonly PublishedArticleReadRepositoryInterface $articles,
        private readonly AboutInfoReadRepositoryInterface $about,
    ) {
    }

    public function build(Project $project, string $locale): ProjectDetailDto
    {
        $data = ProjectDataDto::fromEntity($project);

        return new ProjectDetailDto(
            project: $data,
            techWriteup: $this->techWriteupOf($project),
            seo: ProjectSeoDto::forDetail($data, $locale, $this->about->findSiteName($locale)),
        );
    }

    public function buildForPreview(Project $project, string $locale): ProjectDetailDto
    {
        $data = ProjectDataDto::fromEntity($project);

        return new ProjectDetailDto(
            project: $data,
            techWriteup: $this->techWriteupOf($project),
            seo: ProjectSeoDto::forPreview($data, $locale),
        );
    }

    private function techWriteupOf(Project $project): ?TechWriteupLinkDto
    {
        $articleId = $project->toTechWriteupArticleId();

        if ($articleId === null) {
            return null;
        }

        $article = $this->articles->findPublishedById($articleId);

        return $article === null
            ? null
            : new TechWriteupLinkDto(
                id: $article['id'],
                slug: $article['slug'],
                title: $article['title'],
            );
    }
}
