<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Shared\Domain\BaseEntity;

/**
 * The single-row about block (`about_info`, id = 1 enforced at the
 * application level — database.md:71-88): the hero photo, per-locale
 * alt text, and the non-translatable presentation flags. Localized text
 * lives in {@see AboutInfoTranslation}; `resume_file` is the legacy
 * column managed by the Resume feature and deliberately absent here.
 */
final class AboutInfo extends BaseEntity
{
    /**
     * @param  array<string, string|null>  $photoAlt  Per-locale alt text (locale => text|null).
     * @param  AboutInfoTranslation[]  $translations
     */
    public function __construct(
        private readonly int $id,
        private readonly ?string $photo,
        private readonly array $photoAlt,
        private readonly bool $showLocation,
        private readonly ?string $timezone,
        private readonly bool $relocationReadiness,
        private readonly ?AvailabilityStatus $availabilityStatus,
        private readonly string $workType,
        private readonly array $translations,
    ) {
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toPhoto(): ?string
    {
        return $this->photo;
    }

    /**
     * @return array<string, string|null>
     */
    public function toPhotoAlt(): array
    {
        return $this->photoAlt;
    }

    public function toShowLocation(): bool
    {
        return $this->showLocation;
    }

    public function toTimezone(): ?string
    {
        return $this->timezone;
    }

    public function toRelocationReadiness(): bool
    {
        return $this->relocationReadiness;
    }

    public function toAvailabilityStatus(): ?AvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function toWorkType(): string
    {
        return $this->workType;
    }

    /**
     * @return AboutInfoTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    public function translationFor(string $locale): ?AboutInfoTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->toLocale() === $locale) {
                return $translation;
            }
        }

        foreach ($this->translations as $translation) {
            if ($translation->toLocale() === 'en') {
                return $translation;
            }
        }

        return null;
    }
}
