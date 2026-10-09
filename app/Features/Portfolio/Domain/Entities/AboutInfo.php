<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Portfolio\Domain\ValueObjects\WorkType;
use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

/**
 * The single-row about block (`about_info`, id = 1 enforced at the
 * application level): the hero photo (as a {@see StoredPicture} carrying
 * the RustFS path + per-locale alts) and the non-translatable presentation
 * flags. Localized text lives in {@see AboutInfoTranslation} value objects;
 * `resume_file` is the legacy column managed by the Resume feature and
 * deliberately absent here.
 */
final class AboutInfo extends BaseEntity
{
    /**
     * @param  AboutInfoTranslation[]  $translations
     */
    public function __construct(
        private readonly int $id,
        private readonly ?StoredPicture $photo,
        private readonly bool $showLocation,
        private readonly ?string $timezone,
        private readonly bool $relocationReadiness,
        private readonly ?AvailabilityStatus $availabilityStatus,
        private readonly WorkType $workType,
        private readonly array $translations,
    ) {
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toPhoto(): ?StoredPicture
    {
        return $this->photo;
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

    public function toWorkType(): WorkType
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

    /**
     * Translations keyed by locale (`locale => AboutInfoTranslation`) —
     * the map form used by controllers/use cases that need per-locale
     * access (e.g. the admin form's per-locale fields).
     *
     * @return array<string, AboutInfoTranslation>
     */
    public function translationsByLocale(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->toLocale()] = $translation;
        }

        return $map;
    }

    public function translationFor(string $locale): ?AboutInfoTranslation
    {
        $translations = $this->translationsByLocale();

        return $translations[$locale]
            ?? $translations['en']
            ?? null;
    }
}
