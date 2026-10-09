<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\ContactIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use InvalidArgumentException;

/**
 * A contact entry shown on the landing page and in the resume: localized
 * display names ({@see ContactTranslation} value objects) plus the
 * non-translatable value/icon/url and its position in the reorderable
 * list (`sort_order`, zero-indexed). Invariants are enforced by the
 * shared value objects ({@see ContactIcon}, {@see SortOrder}).
 */
final class Contact extends BaseEntity
{
    private readonly ?ContactIcon $icon;

    private readonly SortOrder $sortOrder;

    /**
     * @param  ContactTranslation[]  $translations
     */
    public function __construct(
        private readonly int $id,
        private readonly string $value,
        ?string $icon,
        private readonly bool $includeInResume,
        private readonly ?string $url,
        int $sortOrder,
        private readonly array $translations,
    ) {
        $this->assertValidValue($value);
        $this->assertValidUrl($url);

        $this->icon = $icon === null ? null : new ContactIcon($icon);
        $this->sortOrder = new SortOrder($sortOrder);
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
        return $this->icon?->toValue();
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
        return $this->sortOrder->toValue();
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
     * requested translation is missing.
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

    private function assertValidUrl(?string $url): void
    {
        if ($url === null) {
            return;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Contact url must be a valid URL, got \"{$url}\".");
        }
    }
}
