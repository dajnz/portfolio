<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\GetLandingPageDataUseCase;
use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest;
use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /` and `GET /{locale}` (routes.md:22,50) — the landing page
 * (public-section-ui.md:56-101): about block, skills, featured projects,
 * contacts, SEO props (seo.md:33-118: Person + WebSite JSON-LD) and the
 * console greeting data (B80). The `{locale}` path constraint
 * (`en|ru`) is enforced at route registration.
 */
final class LandingController
{
    public function __construct(
        private readonly GetLandingPageDataUseCase $landingPageCase,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        $locale = app()->getLocale();
        $data = $this->landingPageCase->get($locale);

        /** @var AboutInfo|null $about */
        $about = $data['about'];
        /** @var TechSkillGroup[] $skills */
        $skills = $data['skills'];
        /** @var FeaturedProject[] $featuredProjects */
        $featuredProjects = $data['featured_projects'];
        /** @var Contact[] $contacts */
        $contacts = $data['contacts'];

        return $inertia->render('Portfolio/Public/Pages/LandingPage', [
            'about' => $about === null ? null : $this->aboutProps($about, $locale),
            'skills' => $this->skillsProps($skills, $locale),
            'featured_projects' => $this->featuredProjectsProps($featuredProjects),
            'has_more' => $data['has_more_projects'],
            'contacts' => $this->contactsProps($contacts),
            'seo' => $this->seoProps($about, $contacts, $request),
            'console_greeting' => [
                'greeting' => __('landing.console_greeting'),
                'name' => $about?->translationFor($locale)?->toName() ?? '',
                'position' => $about?->translationFor($locale)?->toTitle() ?? '',
                'contacts' => array_map(
                    fn (Contact $contact): array => [
                        'label' => $contact->nameFor($locale),
                        'value' => $contact->toValue(),
                    ],
                    $contacts,
                ),
            ],
            'feedback_honeypot_name' => SubmitFeedbackRequest::honeypotFieldName(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function aboutProps(AboutInfo $about, string $locale): array
    {
        $translation = $about->translationFor($locale);

        return [
            'name' => $translation?->toName() ?? '',
            'title' => $translation?->toTitle() ?? '',
            'short_pitch' => $translation?->toShortPitch() ?? '',
            'photo' => $about->toPhoto()?->url,
            'photo_alt' => $about->toPhoto()?->altLabelsMap() ?? [],
            'location' => $translation?->toLocation() ?? '',
            'show_location' => $about->toShowLocation(),
            'timezone' => $about->toTimezone(),
            'availability_status' => $about->toAvailabilityStatus()?->value,
            'work_type' => $about->toWorkType()->value,
            'relocation_readiness' => $about->toRelocationReadiness(),
        ];
    }

    /**
     * @param  TechSkillGroup[]  $groups
     * @return array<int, array<string, mixed>>
     */
    private function skillsProps(array $groups, string $locale): array
    {
        return array_map(static fn (TechSkillGroup $group): array => [
            'id' => $group->toId(),
            'name' => $group->nameFor($locale),
            'icon' => $group->toIcon(),
            'skills' => array_map(
                static fn ($skill): array => [
                    'id' => $skill->toId(),
                    'name' => $skill->toName(),
                    'icon' => $skill->toIcon(),
                    'proficiency' => $skill->toProficiency(),
                ],
                $group->toSkills(),
            ),
        ], $groups);
    }

    /**
     * @param  FeaturedProject[]  $projects
     * @return array<int, array<string, mixed>>
     */
    private function featuredProjectsProps(array $projects): array
    {
        return array_map(static fn (FeaturedProject $project): array => [
            'id' => $project->toId(),
            'title' => $project->toTitle(),
            'slug' => $project->toSlug(),
            'brief_description' => $project->toBriefDescription(),
            'preview_image' => $project->toPreviewImage()?->url,
            'tech_tags' => array_map(
                static fn ($tag): array => [
                    'id' => $tag->toId(),
                    'name' => $tag->toName(),
                    'icon' => $tag->toIcon(),
                ],
                $project->toTechTags(),
            ),
        ], $projects);
    }

    /**
     * @param  Contact[]  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function contactsProps(array $contacts): array
    {
        $locale = app()->getLocale();

        return array_map(static fn (Contact $contact): array => [
            'id' => $contact->toId(),
            'name' => $contact->nameFor($locale),
            'icon' => $contact->toIcon(),
            'value' => $contact->toValue(),
            'url' => $contact->toUrl(),
        ], $contacts);
    }

    /**
     * @return array<string, mixed>
     */
    private function seoProps(?AboutInfo $about, array $contacts, Request $request): array
    {
        $locale = app()->getLocale();
        $translation = $about?->translationFor($locale);
        $name = $translation?->toName() ?? '';

        return [
            'title' => __('seo.title_landing', ['name' => $name]),
            'description' => $translation?->toShortPitch() ?? __('seo.description_landing'),
            'canonical_url' => $request->url(),
            'og_type' => 'website',
            'og_image' => $this->aboutImageUrl($about),
            'json_ld' => $this->jsonLd($about, $contacts),
        ];
    }

    private function aboutImageUrl(?AboutInfo $about): ?string
    {
        if ($about?->toPhoto() === null) {
            return null;
        }

        return url('/assets/'.$about->toPhoto()->url);
    }

    /**
     * Person + WebSite (with SearchAction) blocks per seo.md:86-118.
     *
     * @param  Contact[]  $contacts
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(?AboutInfo $about, array $contacts): array
    {
        $locale = app()->getLocale();
        $translation = $about?->translationFor($locale);
        $name = $translation?->toName() ?? '';

        $sameAs = [];
        foreach ($contacts as $contact) {
            if ($contact->toUrl() !== null) {
                $sameAs[] = $contact->toUrl();
            }
        }

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $name,
                'jobTitle' => __('seo.jsonld_person_job_title'),
                'url' => url('/'),
                'image' => $this->aboutImageUrl($about),
                'sameAs' => $sameAs,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => url('/'),
                'description' => $translation?->toShortPitch() ?? '',
                'inLanguage' => $this->locales->activeLocales(),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => url('/blog?q={search_term_string}'),
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
