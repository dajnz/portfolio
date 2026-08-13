<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\DTOs\RssFeedDto;
use App\Features\Blog\App\UseCases\GetRssFeedUseCase;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /rss` (routes.md:218, seo.md:233-238): the RSS 2.0 feed of
 * the 20 most recent published blog articles — per-item title, link,
 * description, pubDate, author, the lead-image enclosure and the
 * category names (article tags + attached skills, each as a separate
 * `<category>`). The XML is built here from the app-layer feed DTO —
 * the transport shape is a controller concern. The locale middleware
 * resolves the names per the visitor's cookie/URL.
 */
final class FeedController
{
    public function __construct(
        private readonly GetRssFeedUseCase $feedCase,
    ) {
    }

    public function rss(): HttpResponse
    {
        $feed = $this->feedCase->get(app()->getLocale());

        return response($this->toXml($feed), Response::HTTP_OK, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    private function toXml(RssFeedDto $feed): string
    {
        $items = '';

        foreach ($feed->items as $item) {
            $items .= '<item>'
                .'<title>'.self::escape($item->title).'</title>'
                .'<link>'.self::escape($item->link).'</link>'
                .'<description>'.self::escape($item->description).'</description>'
                .'<pubDate>'.$item->publishedAt->format(DATE_RSS).'</pubDate>'
                .'<author>'.self::escape($item->author).'</author>'
                .implode('', array_map(
                    static fn (string $category): string => '<category>'.self::escape($category).'</category>',
                    $item->categories,
                ))
                .($item->enclosureUrl === null
                    ? ''
                    : '<enclosure url="'.self::escape($item->enclosureUrl).'" type="image/*" />')
                .'</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.self::escape($feed->title).'</title>'
            .'<link>'.self::escape($feed->link).'</link>'
            .'<description>'.self::escape($feed->description).'</description>'
            .$items
            .'</channel></rss>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
