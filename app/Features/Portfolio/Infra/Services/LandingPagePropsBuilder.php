<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Services;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\Contact;
use App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest;
use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Infra\Services\PublicPageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

/**
 * Builds the landing-page props from the app-layer entities and caches the
 * result in the public page cache (keyed by locale and request path, because
 * the SEO block carries the canonical URL). Extracted from the controller so
 * caching stays a presentation concern and the controller only orchestrates.
 */
final class LandingPagePropsBuilder
{
    public function __construct(
        private readonly PublicPageCache $cache,
    ) {
    }

    /**
     * @param  Closure(): array<string, mixed>  $entities  the use-case payload,
     *                                                     evaluated only on a cache miss
     * @return array<string, mixed>
     */
    public function build(Request $request, Closure $entities): array
    {
        $locale = App::getLocale();

        return $this->cache->remember(
            'landing:'.$locale.':'.$request->path(),
            fn (): array => $this->props($locale, $request, $entities),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $locale, Request $request, Closure $entities): array
    {
        $data = $entities();

        /** @var AboutInfo|null $about */
        $about = $data['about'];
        /** @var TechSkillGroup[] $skills */
        $skills = $data['skills'];
        /** @var FeaturedProject[] $featuredProjects */
        $featuredProjects = $data['featured_projects'];
        /** @var Contact[] $contacts */
        $contacts = $data['contacts'];
        /** @var string[] $supportedLocales */
        $supportedLocales = $data['supported_locales'];

        return [
            'about' => $about === null ? null : $this->aboutProps($about, $locale),
            'skills' => $this->skillsProps($skills, $locale),
            'featured_projects' => $this->featuredProjectsProps($featuredProjects),
            'has_more' => $data['has_more_projects'],
            'contacts' => $this->contactsProps($contacts),
            'seo' => $this->seoProps(
                about: $about,
                contacts: $contacts,
                supportedLocales: $supportedLocales,
                request: $request,
            ),
            'feedback_honeypot_name' => SubmitFeedbackRequest::honeypotFieldName(),
        ];
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
                static fn (TechSkillTag $tag): array => [
                    'id' => $tag->toId(),
                    'name' => $tag->toSkillName(),
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
        $locale = App::getLocale();

        return array_map(static fn (Contact $contact): array => [
            'id' => $contact->toId(),
            'name' => $contact->nameFor($locale),
            'icon' => $contact->toIcon(),
            'value' => $contact->toValue(),
            'url' => $contact->toUrl(),
        ], $contacts);
    }

    /**
     * @param  Contact[]  $contacts
     * @param  string[]  $supportedLocales
     * @return array<string, mixed>
     */
    private function seoProps(
        ?AboutInfo $about,
        array $contacts,
        array $supportedLocales,
        Request $request,
    ): array {
        $locale = App::getLocale();
        $translation = $about?->translationFor($locale);
        $name = $translation?->toName() ?? '';

        return [
            'title' => __('seo.title_landing', ['name' => $name]),
            'description' => $translation?->toShortPitch() ?? __('seo.description_landing'),
            'canonical_url' => $request->url(),
            'og_url' => $request->url(),
            'og_type' => 'website',
            'og_image' => $this->aboutImageUrl($about),
            'json_ld' => $this->jsonLd($about, $contacts, $supportedLocales),
        ];
    }

    private function aboutImageUrl(?AboutInfo $about): ?string
    {
        if ($about?->toPhoto() === null) {
            return null;
        }

        return URL::to('/assets/'.$about->toPhoto()->url);
    }

    /**
     * Person + WebSite (with SearchAction) blocks.
     *
     * @param  Contact[]  $contacts
     * @param  string[]  $supportedLocales
     * @return array<int, array<string, mixed>>
     */
    private function jsonLd(?AboutInfo $about, array $contacts, array $supportedLocales): array
    {
        $locale = App::getLocale();
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
                'url' => URL::to('/'),
                'image' => $this->aboutImageUrl($about),
                'sameAs' => $sameAs,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $name,
                'url' => URL::to('/'),
                'description' => $translation?->toShortPitch() ?? '',
                'inLanguage' => $supportedLocales,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => URL::to('/blog?q={search_term_string}'),
                    ],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
