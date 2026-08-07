<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * A contact entry shown on the landing page and in the resume
 * (database.md:167-189, content-management.md:105-110): a localized
 * display name plus the non-translatable value/icon/url and its position
 * in the reorderable list (`sort_order`, zero-indexed per A4).
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
        if ($value === '') {
            throw new InvalidArgumentException('Contact value must not be empty.');
        }

        if ($sortOrder < 0) {
            throw new InvalidArgumentException('Contact sort order must not be negative.');
        }
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
     * Localized name for the given locale, falling back to `en` when the
     * requested translation is missing (content-management.md:415).
     */
    public function nameFor(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->toLocale() === $locale) {
                return $translation->toName();
            }
        }

        foreach ($this->translations as $translation) {
            if ($translation->toLocale() === 'en') {
                return $translation->toName();
            }
        }

        return '';
    }
}
