<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use InvalidArgumentException;

/**
 * A project screenshot (database.md:261-273): the RustFS image
 * reference as a {@see StoredPicture} (the path plus the per-locale
 * `alt_text` / `description` jsonb maps — each locale optional;
 * renderers fall back to the project title + "screenshot N" when a
 * locale value is missing), the per-locale `title` map (the short
 * heading, same fallback rules) and its zero-based `sort_order` (the
 * gallery drag order).
 *
 * Invariants: the image path is mandatory (enforced by
 * {@see StoredPicture}); the per-locale maps may be empty but every
 * PRESENT value must be a non-empty string; the position in the
 * gallery is a non-negative {@see SortOrder}.
 */
final class ProjectScreenshot extends BaseEntity
{
    /**
     * @param  array<string, string>  $titles  Locale => short heading.
     */
    public function __construct(
        private int $id,
        private StoredPicture $picture,
        private array $titles,
        private SortOrder $sortOrder,
    ) {
        $this->assertMapValues($titles, 'title');
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toPicture(): StoredPicture
    {
        return $this->picture;
    }

    public function toImagePath(): string
    {
        return $this->picture->url;
    }

    /**
     * @return array<string, string>
     */
    public function altLabelsByLocale(): array
    {
        return $this->picture->altLabelsMap();
    }

    /**
     * @return array<string, string>
     */
    public function titlesByLocale(): array
    {
        return $this->titles;
    }

    /**
     * @return array<string, string>
     */
    public function descriptionsByLocale(): array
    {
        return $this->picture->descriptionsMap();
    }

    public function toSortOrder(): SortOrder
    {
        return $this->sortOrder;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function assertMapValues(array $map, string $field): void
    {
        foreach ($map as $locale => $value) {
            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException(
                    "Project screenshot {$field} for locale '{$locale}' must be a non-empty string.",
                );
            }
        }
    }
}
