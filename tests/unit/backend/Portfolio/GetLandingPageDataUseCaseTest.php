<?php

declare(strict_types=1);

use App\Features\Portfolio\App\UseCases\GetLandingPageDataUseCase;
use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use Tests\Fakes\InMemoryAboutInfoRepository;
use Tests\Fakes\InMemoryContactRepository;
use Tests\Fakes\InMemoryProjectReadRepository;
use Tests\Fakes\InMemoryTechSkillReadRepository;

function landingProject(int $id): FeaturedProject
{
    return new FeaturedProject(
        id: $id,
        title: 'Project '.$id,
        slug: 'project-'.$id,
        briefDescription: 'Description '.$id,
        previewImage: null,
        techTags: [
            new TechSkillTag(id: 1, skillName: 'Laravel', icon: new SkillIcon('devicon:laravel')),
        ],
    );
}

describe('GetLandingPageDataUseCase', function (): void {
    it('assembles about, skills, featured projects and contacts for the locale', function (): void {
        $about = new AboutInfo(
            id: 1,
            photo: null,
            showLocation: true,
            timezone: 'Europe/Berlin',
            relocationReadiness: false,
            availabilityStatus: AvailabilityStatus::Available,
            workType: \App\Features\Portfolio\Domain\ValueObjects\WorkType::Remote,
            translations: [
                new AboutInfoTranslation(locale: 'en', name: 'Alex', title: 'Dev', shortPitch: 'P.', location: 'Berlin'),
            ],
        );
        $skills = [
            new TechSkillGroup(id: 1, icon: new SkillGroupIcon('heroicons:server'), sortOrder: new SortOrder(0), groupNames: [
                new TechSkillGroupName(locale: 'en', name: 'Backend'),
            ], skills: [
                new TechSkill(id: 1, name: 'Laravel', icon: new SkillIcon('devicon:laravel'), proficiency: new ProficiencyLevel(5), includeInResume: true, sortOrder: new SortOrder(0), group: new SkillGroupVo(id: 1, groupNames: [
                    new TechSkillGroupName(locale: 'en', name: 'Backend'),
                ])),
            ]),
        ];
        $contacts = [
            new Contact(id: 1, value: 'octocat', icon: 'simple-icons:github', includeInResume: true, url: 'https://github.com/x', sortOrder: 0, translations: [
                new ContactTranslation(locale: 'en', name: 'GitHub'),
            ]),
        ];

        $useCase = new GetLandingPageDataUseCase(
            aboutInfo: new InMemoryAboutInfoRepository($about),
            skills: new InMemoryTechSkillReadRepository(
                skills: [],
                groups: $skills,
            ),
            projects: new InMemoryProjectReadRepository(
                featured: [landingProject(1)],
                publishedCount: 3,
            ),
            contacts: new InMemoryContactRepository($contacts),
        );

        $data = $useCase->get('en');

        expect($data['about'])->toBe($about)
            ->and($data['skills'])->toBe($skills)
            ->and($data['featured_projects'])->toHaveCount(1)
            ->and($data['featured_projects'][0]->toTitle())->toBe('Project 1')
            ->and($data['featured_projects'][0]->toTechTags())->toHaveCount(1)
            ->and($data['has_more_projects'])->toBeTrue()
            ->and($data['contacts'])->toBe($contacts);
    });

    it('reports has_more_projects as false when all published projects are featured', function (): void {
        $useCase = new GetLandingPageDataUseCase(
            aboutInfo: new InMemoryAboutInfoRepository(null),
            skills: new InMemoryTechSkillReadRepository([]),
            projects: new InMemoryProjectReadRepository(
                featured: [landingProject(1), landingProject(2)],
                publishedCount: 2,
            ),
            contacts: new InMemoryContactRepository([]),
        );

        expect($useCase->get('en')['has_more_projects'])->toBeFalse();
    });

    it('tolerates a missing about row', function (): void {
        $useCase = new GetLandingPageDataUseCase(
            aboutInfo: new InMemoryAboutInfoRepository(null),
            skills: new InMemoryTechSkillReadRepository([]),
            projects: new InMemoryProjectReadRepository(),
            contacts: new InMemoryContactRepository([]),
        );

        $data = $useCase->get('en');

        expect($data['about'])->toBeNull()
            ->and($data['skills'])->toBe([])
            ->and($data['featured_projects'])->toBe([])
            ->and($data['contacts'])->toBe([]);
    });
});
