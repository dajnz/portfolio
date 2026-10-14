<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The project pages' SEO data: the title/description, the canonical URL, the
 * Open Graph type + image, the optional robots directive and the JSON-LD
 * blocks. The callers supply the localized strings; the controller maps this
 * into the wire `SeoProps` shape.
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
        public ?string $ogImage = null,
        public array $jsonLd = [],
        public ?string $robots = null,
    ) {
    }

    public static function forIndex(string $title, string $description, string $baseUrl): self
    {
        return new self(
            title: $title,
            description: $description,
            canonicalUrl: $baseUrl.'/projects',
            ogType: 'website',
        );
    }

    /**
     * @param  string  $seoTitle  The localized `<project> — <site>` title.
     * @param  string  $projectsLabel  The localized breadcrumb label of the projects index.
     */
    public static function forDetail(
        ProjectDataDto $project,
        string $locale,
        string $baseUrl,
        string $seoTitle,
        string $projectsLabel,
    ): self {
        return new self(
            title: $seoTitle,
            description: self::localeText($project->translations->briefDescriptions, $locale),
            canonicalUrl: $baseUrl.'/projects/'.$project->slug,
            ogType: 'article',
            ogImage: self::imageUrl($project, $baseUrl),
            jsonLd: self::jsonLd($project, $locale, $baseUrl, $projectsLabel),
        );
    }

    /**
     * The signed preview: the same title/description with the `noindex`
     * robots directive — preview pages must never enter the index.
     */
    public static function forPreview(
        ProjectDataDto $project,
        string $locale,
        string $baseUrl,
        string $seoTitle,
    ): self {
        return new self(
            title: $seoTitle,
            description: self::localeText($project->translations->briefDescriptions, $locale),
            canonicalUrl: $baseUrl.'/projects/'.$project->slug,
            ogType: 'article',
            ogImage: self::imageUrl($project, $baseUrl),
            robots: 'noindex',
        );
    }

    /**
     * The absolute `og:image` URL of the project's preview image —
     * `null` when no preview is set (the frontend falls back to the
     * default OG image).
     */
    private static function imageUrl(ProjectDataDto $project, string $baseUrl): ?string
    {
        $path = $project->preview?->url;

        return $path === null || $path === '' ? null : $baseUrl.'/assets/'.$path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function jsonLd(
        ProjectDataDto $project,
        string $locale,
        string $baseUrl,
        string $projectsLabel,
    ): array {
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
                        'name' => $projectsLabel,
                        'item' => $baseUrl.'/projects',
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
     * The locale-resolved text with the `en` fallback — the resolution the
     * SEO title and JSON-LD use. Exposed so callers composing the localized
     * title apply the same fallback.
     *
     * @param  array<string, string|null>  $map
     */
    public static function localeText(array $map, string $locale): string
    {
        $value = $map[$locale] ?? $map['en'] ?? null;

        return is_string($value) ? $value : '';
    }
}
