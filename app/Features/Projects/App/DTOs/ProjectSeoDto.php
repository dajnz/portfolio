<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The projects pages' SEO data (seo.md:18-31): the localized title/
 * description, the canonical URL, the Open Graph type, the optional
 * robots directive and the JSON-LD blocks (seo.md:150-188 —
 * SoftwareSourceCode + BreadcrumbList on the detail page). Built by
 * the app layer (the site name and the canonical URL need non-HTTP
 * sources); the controllers map it into their output shapes.
 * Immutable.
 */
final readonly class ProjectSeoDto
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $ogType,
        public array $jsonLd = [],
        public ?string $robots = null,
    ) {
    }

    /**
     * The public index (seo.md:43).
     */
    public static function forIndex(string $locale, string $siteName): self
    {
        return new self(
            title: __('seo.title_projects_index', ['name' => $siteName]),
            description: __('seo.description_projects_index', ['name' => $siteName]),
            canonicalUrl: self::baseUrl().'/projects',
            ogType: 'website',
        );
    }

    /**
     * The public detail (seo.md:150-188): the localized title/
     * description, the slashless canonical URL and the JSON-LD
     * SoftwareSourceCode + BreadcrumbList pair.
     */
    public static function forDetail(ProjectDataDto $project, string $locale, string $siteName): self
    {
        return new self(
            title: __('seo.title_project_detail', [
                'project_title' => self::localeText($project->translations->titles, $locale),
                'name' => $siteName,
            ]),
            description: self::localeText($project->translations->briefDescriptions, $locale),
            canonicalUrl: self::baseUrl().'/projects/'.$project->slug,
            ogType: 'article',
            jsonLd: self::jsonLd($project, $locale),
        );
    }

    /**
     * The signed preview (routes.md:58-60): the same title/description
     * with the `noindex` robots directive — preview pages must never
     * enter the index (seo.md).
     */
    public static function forPreview(ProjectDataDto $project, string $locale): self
    {
        return new self(
            title: __('seo.title_project_detail', [
                'project_title' => self::localeText($project->translations->titles, $locale),
                'name' => '',
            ]),
            description: self::localeText($project->translations->briefDescriptions, $locale),
            canonicalUrl: self::baseUrl().'/projects/'.$project->slug,
            ogType: 'article',
            robots: 'noindex',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function jsonLd(ProjectDataDto $project, string $locale): array
    {
        $sourceCode = [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareSourceCode',
            'name' => self::localeText($project->translations->titles, $locale),
            'description' => self::localeText($project->translations->briefDescriptions, $locale),
            'programmingLanguage' => array_values(array_filter(array_map(
                static fn (SkillOptionDto $tag): string => $tag->name,
                $project->techTags,
            ))),
        ];

        if ($project->links->sourceCodeUrl !== null) {
            $sourceCode['codeRepository'] = $project->links->sourceCodeUrl;
        }

        return [
            $sourceCode,
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => __('nav.projects'),
                        'item' => self::baseUrl().'/projects',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => self::localeText($project->translations->titles, $locale),
                    ],
                ],
            ],
        ];
    }

    /**
     * The locale-resolved text with the `en` fallback
     * (content-management.md:415).
     *
     * @param  array<string, string|null>  $map
     */
    private static function localeText(array $map, string $locale): string
    {
        $value = $map[$locale] ?? $map['en'] ?? null;

        return is_string($value) ? $value : '';
    }

    private static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
