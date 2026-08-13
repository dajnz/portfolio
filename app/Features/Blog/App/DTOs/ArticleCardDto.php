<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use DateTimeImmutable;

/**
 * An article card of the public blog index — the `ArticleCardData`
 * wire shape (blog.yaml#ArticleCardData). `snippet` carries the
 * `ts_headline()` search excerpt (the `<mark>`-wrapped HTML — B16)
 * when the request searched; the controller substitutes it for the
 * `short_description` in the HTML variant (the JSON variant returns
 * raw values). Immutable.
 */
final readonly class ArticleCardDto
{
    /**
     * @param  TagOptionDto[]  $tags
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $shortDescription,
        public ?DateTimeImmutable $publishedAt,
        public int $readingTimeMinutes,
        public ?string $leadImage,
        public ?string $snippet,
        public array $tags = [],
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            id: (int) $row->id,
            slug: $row->slug,
            title: $row->title,
            shortDescription: $row->short_description,
            publishedAt: $row->published_at === null
                ? null
                : new DateTimeImmutable($row->published_at),
            readingTimeMinutes: (int) $row->reading_time_minutes,
            leadImage: $row->lead_image === null ? null : (string) $row->lead_image,
            snippet: self::sanitizeSnippet(
                property_exists($row, 'fts_snippet') && $row->fts_snippet !== null
                    ? (string) $row->fts_snippet
                    : null,
            ),
        );
    }

    /**
     * The `ts_headline` output is the raw article text with `<mark>`
     * tags — every OTHER tag is escaped so an admin-authored raw-HTML
     * snippet can never execute (the pipeline's safe-mode applies to
     * `content_html`; the headline runs over the raw stored content).
     */
    private static function sanitizeSnippet(?string $snippet): ?string
    {
        if ($snippet === null) {
            return null;
        }

        $protected = str_replace(['<mark>', '</mark>'], ["\x01", "\x02"], $snippet);

        return str_replace(
            ["\x01", "\x02"],
            ['<mark>', '</mark>'],
            htmlspecialchars($protected, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
