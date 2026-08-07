<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A stored image reference shared by every feature that works with
 * uploaded images (about photo, project preview images, article lead
 * images — content-management.md:14,198,334): the RustFS path plus the
 * per-locale alt labels and optional per-locale descriptions (the DB
 * `*_alt` jsonb columns, e.g. `photo_alt` / `preview_image_alt`).
 *
 * The `url` is mandatory; every PRESENT alt label must be non-empty
 * (no blank entries), but the map itself may be empty — the admin forms
 * allow per-locale alt values to be null (content-management.md:572-586),
 * and renderers fall back to the entity's name/title for missing alts
 * (content-management.md:14,198). The `descriptions` map is optional and
 * may be empty.
 */
final readonly class StoredPicture
{
    /**
     * @param  array<string, string>  $altLabels  Locale => short alt label.
     * @param  array<string, string>  $descriptions  Locale => description (may be empty).
     */
    public function __construct(
        public string $url,
        public array $altLabels,
        public array $descriptions = [],
    ) {
        if ($url === '') {
            throw new InvalidArgumentException('Stored picture URL must not be empty.');
        }

        $this->assertMapValues($altLabels, 'alt label');
        $this->assertMapValues($descriptions, 'description');
    }

    /**
     * Alt label for the given locale, falling back to `en`.
     */
    public function altLabel(string $locale): string
    {
        return $this->altLabels[$locale]
            ?? $this->altLabels['en']
            ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function altLabelsMap(): array
    {
        return $this->altLabels;
    }

    /**
     * Description for the given locale, falling back to `en`.
     */
    public function description(string $locale): ?string
    {
        return $this->descriptions[$locale]
            ?? $this->descriptions['en']
            ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function descriptionsMap(): array
    {
        return $this->descriptions;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function assertMapValues(array $map, string $field): void
    {
        foreach ($map as $locale => $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException(
                    "Stored picture {$field} for locale '{$locale}' must be a non-empty string.",
                );
            }
        }
    }
}
