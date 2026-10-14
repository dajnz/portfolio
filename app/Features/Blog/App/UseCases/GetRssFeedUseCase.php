<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\App\Contracts\UrlGeneratorInterface;
use App\Features\Shared\App\DTOs\RssFeedDto;
use App\Features\Shared\App\DTOs\RssItemDto;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;

/**
 * The RSS feed data: the 20 most recent published articles with the
 * localized titles/descriptions, the canonical links, the site name as
 * the author, the category names (article tags + attached skills —
 * each as a separate `<category>`) and the lead-image enclosure. The
 * channel title/link come from the SEO index keys and the app URL;
 * the XML itself is built by the shared
 * {@see \App\Features\Shared\App\Services\RssXmlBuilder}.
 */
final class GetRssFeedUseCase
{
    private const FEED_LIMIT = 20;

    public function __construct(
        private readonly PublicArticleReadRepositoryInterface $repository,
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function get(string $locale): RssFeedDto
    {
        $siteName = $this->about->findSiteName($locale);
        $rows = $this->repository->findRssRows($locale, self::FEED_LIMIT);

        $articleIds = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $tagsByArticle = $this->repository->articleTagsFor($articleIds);
        $skillsByArticle = $this->repository->skillTagsFor($articleIds);

        return new RssFeedDto(
            title: $this->translator->translate('seo.title_blog_index', ['name' => $siteName]),
            description: $this->translator->translate('seo.description_blog_index', ['name' => $siteName]),
            link: $this->urls->to('/'),
            items: array_map(
                fn (object $row): RssItemDto => new RssItemDto(
                    title: $row->title,
                    link: $this->urls->to('/blog/'.$row->slug),
                    description: $row->short_description,
                    publishedAt: new \DateTimeImmutable($row->published_at),
                    author: $siteName,
                    categories: [
                        ...array_map(static fn (object $tag): string => $tag->name, $tagsByArticle[(int) $row->id] ?? []),
                        ...array_map(static fn ($skill): string => $skill->toSkillName(), $skillsByArticle[(int) $row->id] ?? []),
                    ],
                    enclosureUrl: $row->lead_image === null
                        ? null
                        : $this->urls->to('/assets/'.$row->lead_image),
                ),
                $rows,
            ),
        );
    }
}
