<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Services;

use App\Features\Blog\App\DTOs\BlogSeoDto;
use App\Features\Shared\App\Contracts\ApplicationUrlInterface;
use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use DateTimeImmutable;

/**
 * Builds the blog pages' SEO DTOs — resolves the site name (and photo), the
 * localized titles and the absolute URLs, so the public blog use cases only
 * supply the page data.
 */
final class BlogSeoFactory
{
    public function __construct(
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly TranslatorInterface $translator,
        private readonly ApplicationUrlInterface $applicationUrl,
    ) {
    }

    public function forIndex(string $locale): BlogSeoDto
    {
        $siteName = $this->about->findSiteName($locale);

        return BlogSeoDto::forIndex(
            title: $this->translator->translate('seo.title_blog_index', ['name' => $siteName]),
            description: $this->translator->translate('seo.description_blog_index', ['name' => $siteName]),
            baseUrl: $this->applicationUrl->baseUrl(),
        );
    }

    /**
     * @param  string[]  $tagNames
     * @param  string[]  $skillNames
     */
    public function forDetail(
        string $locale,
        string $title,
        string $description,
        string $slug,
        ?string $leadImagePath,
        array $tagNames,
        array $skillNames,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $updatedAt,
    ): BlogSeoDto {
        $siteName = $this->about->findSiteName($locale);

        return BlogSeoDto::forDetail(
            title: $title,
            description: $description,
            seoTitle: $this->translator->translate(
                'seo.title_blog_article',
                ['article_title' => $title, 'name' => $siteName],
            ),
            blogLabel: $this->translator->translate('nav.blog'),
            slug: $slug,
            leadImagePath: $leadImagePath,
            siteName: $siteName,
            baseUrl: $this->applicationUrl->baseUrl(),
            tagNames: $tagNames,
            skillNames: $skillNames,
            publishedAt: $publishedAt,
            updatedAt: $updatedAt,
            sitePhotoPath: $this->about->findSitePhotoPath(),
        );
    }
}
