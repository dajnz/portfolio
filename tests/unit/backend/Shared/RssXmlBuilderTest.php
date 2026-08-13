<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\App\DTOs\RssFeedDto;
use App\Features\Shared\App\DTOs\RssItemDto;
use App\Features\Shared\App\Services\RssXmlBuilder;
use DateTimeImmutable;

/**
 * The generic RSS 2.0 XML builder (step 8.2 rework): pure string
 * construction with full XML escaping — reusable by any feature that
 * needs a feed.
 */
describe('RssXmlBuilder', function (): void {
    it('builds the channel and items structure', function (): void {
        $xml = (new RssXmlBuilder())->build(rssFeedFixture());

        expect($xml)->toBeString()
            ->and($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
            ->and($xml)->toContain('<rss version="2.0"><channel>')
            ->and($xml)->toContain('<title>Blog — Portfolio</title>')
            ->and($xml)->toContain('<link>https://example.com</link>')
            ->and($xml)->toContain('<description>Feed description</description>')
            ->and($xml)->toContain('<item><title>Post</title>')
            ->and($xml)->toContain('<pubDate>Thu, 15 Jan 2026 12:00:00 +0000</pubDate>')
            ->and($xml)->toContain('<author>Portfolio</author>')
            ->and($xml)->toContain('</channel></rss>');
    });

    it('escapes XML-special characters in every text node', function (): void {
        $feed = new RssFeedDto(
            title: 'A & B <feed>',
            description: 'desc "quoted" & more',
            link: 'https://example.com/?a=1&b=2',
            items: [
                new RssItemDto(
                    title: 'X < & > " \'',
                    link: 'https://example.com/?x=1&y=2',
                    description: 'd <b> & "',
                    publishedAt: new DateTimeImmutable('2026-01-15 12:00:00'),
                    author: 'a & b',
                    categories: ['c1 & c2', 'c3 < c4'],
                    enclosureUrl: 'https://example.com/img.png?a=1&b=2',
                ),
            ],
        );

        $xml = (new RssXmlBuilder())->build($feed);

        expect($xml)->not->toContain('& <')
            ->and($xml)->toContain('<title>A &amp; B &lt;feed&gt;</title>')
            ->and($xml)->toContain('<description>desc &quot;quoted&quot; &amp; more</description>')
            ->and($xml)->toContain('<link>https://example.com/?a=1&amp;b=2</link>')
            ->and($xml)->toContain('<title>X &lt; &amp; &gt; &quot; &apos;</title>')
            ->and($xml)->toContain('<category>c1 &amp; c2</category>')
            ->and($xml)->toContain('<category>c3 &lt; c4</category>')
            // The XML is well-formed: every tag closes.
            ->and(preg_match_all('/<(title|link|description|category|item|channel|rss)\b/', $xml))
            ->toBe(preg_match_all('/<\/(title|link|description|category|item|channel|rss)>/', $xml));
    });

    it('emits the enclosure only when the URL is set', function (): void {
        $with = (new RssXmlBuilder())->build(rssFeedFixture(enclosure: 'https://example.com/cover.png'));
        $without = (new RssXmlBuilder())->build(rssFeedFixture());

        expect($with)->toContain('<enclosure url="https://example.com/cover.png" type="image/*" />')
            ->and($without)->not->toContain('<enclosure');
    });

});

/**
 * A minimal one-item feed fixture.
 */
function rssFeedFixture(?string $enclosure = null): RssFeedDto
{
    return new RssFeedDto(
        title: 'Blog — Portfolio',
        description: 'Feed description',
        link: 'https://example.com',
        items: [
            new RssItemDto(
                title: 'Post',
                link: 'https://example.com/blog/post',
                description: 'A post.',
                publishedAt: new DateTimeImmutable('2026-01-15 12:00:00'),
                author: 'Portfolio',
                categories: ['php'],
                enclosureUrl: $enclosure,
            ),
        ],
    );
}
