<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * A contact entry shown on the landing page and in the resume
 * (database.md:167-189, content-management.md:105-110): localized display
 * names ({@see ContactTranslation} value objects) plus the
 * non-translatable value/icon/url and its position in the reorderable
 * list (`sort_order`, zero-indexed per A4). Invariants are enforced in
 * the constructor via the dedicated validation methods.
 */
final class Contact extends BaseEntity
{
    /**
     * @param  ContactTranslation[]  $translations
     */
    public function __construct(
        private readonly int $id,
        private readonly string $value,
        private readonly ?string $icon,
        private readonly bool $includeInResume,
        private readonly ?string $url,
        private readonly int $sortOrder,
        private readonly array $translations,
    ) {
        $this->assertValidValue($value);
        $this->assertValidIcon($icon);
        $this->assertValidUrl($url);
        $this->assertValidSortOrder($sortOrder);
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toValue(): string
    {
        return $this->value;
    }

    public function toIcon(): ?string
    {
        return $this->icon;
    }

    public function toIncludeInResume(): bool
    {
        return $this->includeInResume;
    }

    public function toUrl(): ?string
    {
        return $this->url;
    }

    public function toSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return ContactTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Translations keyed by locale (`locale => ContactTranslation`).
     *
     * @return array<string, ContactTranslation>
     */
    public function translationsByLocale(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->toLocale()] = $translation;
        }

        return $map;
    }

    /**
     * Localized name for the given locale, falling back to `en` when the
     * requested translation is missing (content-management.md:415).
     */
    public function nameFor(string $locale): string
    {
        $translations = $this->translationsByLocale();
        $translation = $translations[$locale] ?? $translations['en'] ?? null;

        return $translation?->toName() ?? '';
    }

    private function assertValidValue(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('Contact value must not be empty.');
        }
    }

    private function assertValidIcon(?string $icon): void
    {
        if ($icon === null) {
            return;
        }

        if (preg_match('/^[a-z0-9-]+:[a-zA-Z0-9-]+$/', $icon) !== 1) {
            throw new InvalidArgumentException(
                "Contact icon must be an Iconify identifier (\"set:name\"), got \"{$icon}\".",
            );
        }
    }

    private function assertValidUrl(?string $url): void
    {
        if ($url === null) {
            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Contact url must be a valid URL, got \"{$url}\".");
        }
    }

    private function assertValidSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new InvalidArgumentException('Contact sort order must not be negative.');
        }
    }
}
