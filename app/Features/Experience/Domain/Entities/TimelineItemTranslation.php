<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\Entities;

use InvalidArgumentException;

/**
 * A single localized row of `experience_timeline_item_translations`
 * (PK `(item_id, locale)`): the identity fields (mandatory position;
 * optional company name + country) and the required Markdown
 * `short_description` plus the achievements list (jsonb array of
 * `{description}`). Immutable; carries no identity of its own.
 *
 * Invariants:
 * - `short_description` is mandatory — it is the only required column
 *   (the DB column is NOT NULL);
 * - `position` is mandatory too (the DB column is NOT NULL);
 * - the company pair is all-or-none: when any of company name / company
 *   country is set, both must be set (an empty country string is
 *   normalized to null);
 * - achievements are optional — their structure is validated only when
 *   the list is non-empty (the plain-text-length rule for
 *   `short_description` lives in the form request, which also enforces
 *   the active-locale rules).
 *
 * @phpstan-type AchievementData array{description: string}
 */
final readonly class TimelineItemTranslation
{
    /** @var AchievementData[] */
    private array $achievements;

    private ?string $companyCountry;

    /**
     * @param  array<int, array{description: string}>  $achievements
     */
    public function __construct(
        private string $locale,
        private string $position,
        private string $companyName,
        ?string $companyCountry,
        private string $shortDescription,
        array $achievements,
    ) {
        if ($shortDescription === '') {
            throw new InvalidArgumentException('Timeline item translation short description must not be empty.');
        }

        if ($position === '') {
            throw new InvalidArgumentException('Timeline item translation position must not be empty.');
        }

        $this->assertCompanyPair($companyName, $companyCountry);

        $this->companyCountry = $companyCountry === '' ? null : $companyCountry;
        $this->achievements = $this->validateAchievements($achievements);
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toPosition(): string
    {
        return $this->position;
    }

    public function toCompanyName(): string
    {
        return $this->companyName;
    }

    public function toCompanyCountry(): ?string
    {
        return $this->companyCountry;
    }

    public function toShortDescription(): string
    {
        return $this->shortDescription;
    }

    /**
     * @return AchievementData[]
     */
    public function toAchievements(): array
    {
        return $this->achievements;
    }

    private function assertCompanyPair(string $companyName, ?string $companyCountry): void
    {
        $set = 0;
        foreach ([$companyName, $companyCountry] as $value) {
            if ($value !== null && $value !== '') {
                $set++;
            }
        }

        if ($set === 1) {
            throw new InvalidArgumentException(
                'Timeline item translation company name and country must be set together or left both empty.'
            );
        }
    }

    /**
     * Achievements are optional — the structure is validated only when
     * the list is non-empty.
     *
     * @param  array<int, array{description: string}>  $achievements
     * @return AchievementData[]
     */
    private function validateAchievements(array $achievements): array
    {
        if ($achievements === []) {
            return [];
        }

        return array_map(
            $this->validateAchievement(...),
            array_values($achievements),
        );
    }

    /**
     * @param  array{description: string}  $achievement
     * @return AchievementData
     */
    private function validateAchievement(array $achievement): array
    {
        $description = $achievement['description'] ?? '';

        if (! is_string($description) || $description === '') {
            throw new InvalidArgumentException('Achievement description must be a non-empty string.');
        }

        if (mb_strlen($description) > 1000) {
            throw new InvalidArgumentException('Achievement description must not exceed 1000 characters.');
        }

        return ['description' => $description];
    }
}
