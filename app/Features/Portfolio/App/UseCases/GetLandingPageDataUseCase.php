<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * Assembles every landing-page data slice (public-section-ui.md:56-101):
 * the about block, the grouped skills (via the shared tech-skill read
 * model), the featured projects (via the shared project read model, ADR-0003)
 * and the contacts. Returns domain entities — the controller maps them
 * to the page props.
 */
final class GetLandingPageDataUseCase
{
    public function __construct(
        private readonly AboutInfoRepositoryInterface $aboutInfo,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly ProjectReadRepositoryInterface $projects,
        private readonly ContactRepositoryInterface $contacts,
    ) {
    }

    /**
     * @return array{
     *     about: AboutInfo|null,
     *     skills: TechSkillGroup[],
     *     featured_projects: FeaturedProject[],
     *     has_more_projects: bool,
     *     contacts: Contact[],
     * }
     */
    public function get(string $locale): array
    {
        $featuredProjects = $this->projects->findFeatured($locale);

        return [
            'about' => $this->aboutInfo->find(),
            'skills' => $this->skills->findAllGrouped($locale),
            'featured_projects' => $featuredProjects,
            'has_more_projects' => $this->projects->countPublished() > count($featuredProjects),
            'contacts' => $this->contacts->findAll(),
        ];
    }
}
