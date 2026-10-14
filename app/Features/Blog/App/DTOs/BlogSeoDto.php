<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use DateTimeImmutable;

/**
 * The blog pages' SEO data: the localized title/description, the
 * slashless canonical URL, the Open Graph type (+ the absolute
 * lead-image URL for the detail page), the optional robots directive
 * and the JSON-LD blocks (BlogPosting + BreadcrumbList). Built by the
 * app layer; the controller maps it into the wire `SeoProps` shape.
 *
 * The `og:image` is converted to an ABSOLUTE `/assets/` URL here
 * (crawlers reject relative image URLs); the index page leaves it null
 * — the frontend falls back to `/images/og-default.png`. Immutable.
 */
final readonly class BlogSeoDto
{
    /**
     * @param  array<int, array<string, mixed>>  $jsonLd
     * @param  array{published_time: ?string, author: ?string, tags: array<int, string>}|null  $article
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonicalUrl,
        public string $ogType,
        public ?string $ogImage = null,
        public array $jsonLd = [],
        public ?string $robots = null,
        public ?array $article = null,
    ) {
    }

    /**
     * The blog index: the localized site-title pattern, no
     * page-specific image.
     */
    public static function forIndex(string $locale, string $siteName, string $baseUrl): self
    {
        return new self(
            title: __('seo.title_blog_index', ['name' => $siteName]),
            description: __('seo.description_blog_index', ['name' => $siteName]),
            canonicalUrl: $baseUrl.'/blog',
            ogType: 'website',
        );
    }

    /**
     * The article detail: the localized title pattern, the short
     * description, the article OG type with the absolute lead-image URL
     * and the BlogPosting + BreadcrumbList JSON-LD pair.
     *
     * @param  string[]  $tagNames  The article-tag names.
     * @param  string[]  $skillNames  The attached skill names (both feed `keywords`).
     */
    public static function forDetail(
        string $title,
        string $description,
        string $slug,
        ?string $leadImagePath,
        string $siteName,
        string $baseUrl,
        array $tagNames,
        array $skillNames,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $updatedAt,
        ?string $sitePhotoPath = null,
    ): self {
        $canonical = $baseUrl.'/blog/'.$slug;
        $imageUrl = $leadImagePath === null ? null : self::assetUrl($leadImagePath, $baseUrl);
        $logoUrl = $sitePhotoPath === null ? null : self::assetUrl($sitePhotoPath, $baseUrl);

        return new self(
            title: __('seo.title_blog_article', ['article_title' => $title, 'name' => $siteName]),
            description: $description,
            canonicalUrl: $canonical,
            ogType: 'article',
            ogImage: $imageUrl,
            jsonLd: self::jsonLd(
                $title,
                $description,
                $canonical,
                $siteName,
                $tagNames,
                $skillNames,
                $publishedAt,
                $updatedAt,
                $imageUrl,
                $logoUrl,
                $baseUrl,
            ),
            article: [
                'published_time' => $publishedAt?->format('c'),
                'author' => $siteName === '' ? null : $siteName,
                'tags' => array_values(array_unique(array_merge($tagNames, $skillNames))),
            ],
        );
    }

    /**
     * @param  string[]  $tagNames
     * @param  string[]  $skillNames
     * @return array<int, array<string, mixed>>
     */
    private static function jsonLd(
        string $title,
        string $description,
        string $canonical,
        string $siteName,
        array $tagNames,
        array $skillNames,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $updatedAt,
        ?string $imageUrl,
        ?string $logoUrl,
        string $baseUrl,
    ): array {
        $publisher = [
            '@type' => 'Organization',
            'name' => $siteName,
        ];

        if ($logoUrl !== null) {
            $publisher['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logoUrl,
            ];
        }

        $posting = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'url' => $canonical,
            'mainEntityOfPage' => $canonical,
            'author' => [
                '@type' => 'Person',
                'name' => $siteName,
            ],
            'publisher' => $publisher,
            'keywords' => array_values(array_unique(array_merge($tagNames, $skillNames))),
        ];

        if ($imageUrl !== null) {
            $posting['image'] = $imageUrl;
        }

        if ($publishedAt !== null) {
            $posting['datePublished'] = $publishedAt->format('c');
        }

        if ($updatedAt !== null) {
            $posting['dateModified'] = $updatedAt->format('c');
        }

        return [
            $posting,
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => __('nav.blog'),
                        'item' => $baseUrl.'/blog',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $title,
                    ],
                ],
            ],
        ];
    }

    private static function assetUrl(string $path, string $baseUrl): string
    {
        return $baseUrl.'/assets/'.$path;
    }
}
