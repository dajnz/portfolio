<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Services;

use App\Features\Shared\App\DTOs\RssFeedDto;
use App\Features\Shared\App\DTOs\RssItemDto;

/**
 * Builds RSS 2.0 XML from the shared {@see RssFeedDto} data — a generic,
 * framework-free service reusable by any feature that needs a feed. Every
 * text value is XML-escaped (`ENT_XML1` — the whole document stays
 * well-formed regardless of the content); the dates use the RFC 2822
 * format RSS requires.
 *
 * Pure string construction — no framework dependencies, unit-testable
 * without the container.
 */
final class RssXmlBuilder
{
    public function build(RssFeedDto $feed): string
    {
        $items = '';

        foreach ($feed->items as $item) {
            $items .= $this->item($item);
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.self::escape($feed->title).'</title>'
            .'<link>'.self::escape($feed->link).'</link>'
            .'<description>'.self::escape($feed->description).'</description>'
            .$items
            .'</channel></rss>';
    }

    private function item(RssItemDto $item): string
    {
        return '<item>'
            .'<title>'.self::escape($item->title).'</title>'
            .'<link>'.self::escape($item->link).'</link>'
            .'<description>'.self::escape($item->description).'</description>'
            .'<pubDate>'.$item->publishedAt->format(\DateTimeInterface::RFC2822).'</pubDate>'
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

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
