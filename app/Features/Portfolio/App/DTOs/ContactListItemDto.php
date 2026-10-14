<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\DTOs;

/**
 * A contact as an admin list row. The per-locale name map travels alongside
 * the locale-resolved name — the inline editor PUTs the full map back.
 * Immutable.
 */
final readonly class ContactListItemDto
{
    /**
     * @param  array<string, string>  $names  The per-locale name map.
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $names,
        public string $value,
        public ?string $icon,
        public ?string $url,
        public bool $includeInResume,
        public int $sortOrder,
    ) {
    }

    /**
     * @param  object  $row  The contact row with its translations loaded.
     */
    public static function fromRow(object $row, string $locale): self
    {
        $names = [];
        $resolved = null;

        foreach ($row->translations as $translation) {
            $name = (string) $translation->name;
            $names[(string) $translation->locale] = $name;

            if ((string) $translation->locale === $locale) {
                $resolved = $name;
            }
        }

        return new self(
            id: (int) $row->id,
            name: $resolved ?? (string) ($names['en'] ?? ''),
            names: $names,
            value: (string) $row->value,
            icon: $row->icon === null ? null : (string) $row->icon,
            url: $row->url === null ? null : (string) $row->url,
            includeInResume: (bool) $row->include_in_resume,
            sortOrder: (int) $row->sort_order,
        );
    }
}
