<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\Entities;

use App\Features\Experience\Domain\ValueObjects\RelatedProject;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\SortOrder;

/**
 * A career timeline item (database.md:285-305, content-management.md:
 * 164-189): its employment {@see WorkPeriod}, publication state, the
 * optional project reference ({@see RelatedProject} — null when the
 * item references no project), resume inclusion, the zero-based
 * `sort_order`, its per-locale translations and the tech-tag skills of
 * the `experience_item_tech_tags` pivot (part of the aggregate).
 *
 * Invariants are delegated to the embedded value objects and entities:
 * the date order to {@see WorkPeriod}, the project-reference
 * exclusivity to {@see RelatedProject}, the position in the list to
 * {@see SortOrder}, the tag name to {@see TechSkillTag}, and the
 * per-locale field rules to {@see TimelineItemTranslation} — the entity
 * itself is a pure aggregate.
 *
 * Localized state is projected per property: `positionFor($locale)`
 * etc. resolve the requested locale with an `en` fallback
 * (content-management.md:415), the `*ByLocale()` maps expose every
 * stored locale for the admin LocaleTabs, and the achievement
 * projections feed the form's merged achievements list.
 */
final class TimelineItem extends BaseEntity
{
    /**
     * @param  TimelineItemTranslation[]  $translations
     * @param  TechSkillTag[]  $techTags  Skill tags from the `experience_item_tech_tags` pivot.
     */
    public function __construct(
        private readonly int $id,
        private readonly WorkPeriod $period,
        private readonly bool $isPublished,
        private readonly SortOrder $sortOrder,
        private readonly ?RelatedProject $relatedProject,
        private readonly bool $includeInResume,
        private readonly array $translations,
        private readonly array $techTags = [],
    ) {
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toPeriod(): WorkPeriod
    {
        return $this->period;
    }

    public function toIsCurrent(): bool
    {
        return $this->period->isCurrent();
    }

    /**
     * "2020–2026" | "2020–Present" — the year-level period shown on the
     * timeline cards and the admin list. The ongoing-position label is
     * passed in (e.g. `__('experience.present')`) so it renders
     * localized — the domain itself stays framework-free.
     */
    public function toPrettyPeriod(string $presentLabel): string
    {
        return $this->period->toPrettyRange($presentLabel);
    }

    public function toIsPublished(): bool
    {
        return $this->isPublished;
    }

    public function toSortOrder(): SortOrder
    {
        return $this->sortOrder;
    }

    public function toRelatedProject(): ?RelatedProject
    {
        return $this->relatedProject;
    }

    public function toIncludeInResume(): bool
    {
        return $this->includeInResume;
    }

    /**
     * @return TechSkillTag[]
     */
    public function toTechTags(): array
    {
        return $this->techTags;
    }

    /**
     * Localized position for the given locale, falling back to `en`
     * when the requested translation is missing (content-management.
     * md:415).
     */
    public function positionFor(string $locale): string
    {
        return $this->translationFor($locale)?->toPosition() ?? '';
    }

    public function companyNameFor(string $locale): string
    {
        return $this->translationFor($locale)?->toCompanyName() ?? '';
    }

    public function companyCountryFor(string $locale): ?string
    {
        return $this->translationFor($locale)?->toCompanyCountry();
    }

    public function shortDescriptionFor(string $locale): string
    {
        return $this->translationFor($locale)?->toShortDescription() ?? '';
    }

    /**
     * @return array<int, array{description: string, project_link: string|null}>
     */
    public function achievementsFor(string $locale): array
    {
        return $this->translationFor($locale)?->toAchievements() ?? [];
    }

    /**
     * Locale => position — every stored translation row (the admin
     * LocaleTabs render all of them, disabled locales included).
     *
     * @return array<string, string>
     */
    public function positionsByLocale(): array
    {
        return $this->mapTranslations(static fn (TimelineItemTranslation $translation): string => $translation->toPosition());
    }

    /**
     * @return array<string, string>
     */
    public function companyNamesByLocale(): array
    {
        return $this->mapTranslations(static fn (TimelineItemTranslation $translation): string => $translation->toCompanyName());
    }

    /**
     * @return array<string, string|null>
     */
    public function companyCountriesByLocale(): array
    {
        return $this->mapTranslations(static fn (TimelineItemTranslation $translation): ?string => $translation->toCompanyCountry());
    }

    /**
     * @return array<string, string>
     */
    public function shortDescriptionsByLocale(): array
    {
        return $this->mapTranslations(static fn (TimelineItemTranslation $translation): string => $translation->toShortDescription());
    }

    /**
     * Locale => achievement description by index — lets the admin form
     * merge the per-locale achievement lists into one unified editor
     * list (an achievement may describe itself in one language only).
     *
     * @return array<string, array<int, string|null>>
     */
    public function achievementDescriptionsByLocale(): array
    {
        $map = [];
        foreach ($this->translationsByLocale() as $locale => $translation) {
            $map[$locale] = array_map(
                static fn (array $achievement): ?string => $achievement['description'],
                $translation->toAchievements(),
            );
        }

        return $map;
    }

    /**
     * Locale => achievement project link by index (null when the
     * achievement has no link or the locale has no such achievement).
     *
     * @return array<string, array<int, string|null>>
     */
    public function achievementProjectLinksByLocale(): array
    {
        $map = [];
        foreach ($this->translationsByLocale() as $locale => $translation) {
            $map[$locale] = array_map(
                static fn (array $achievement): ?string => $achievement['project_link'],
                $translation->toAchievements(),
            );
        }

        return $map;
    }

    /**
     * The locales with a stored translation row — the exact set the
     * persistence layer reconciles against the active locales (rows of
     * disabled locales are preserved untouched, content-management.md:
     * 419).
     *
     * @return string[]
     */
    public function toTranslationLocales(): array
    {
        return array_keys($this->translationsByLocale());
    }

    /**
     * @return array<string, TimelineItemTranslation>
     */
    private function translationsByLocale(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->toLocale()] = $translation;
        }

        return $map;
    }

    /**
     * Translation for the given locale, falling back to `en` when the
     * requested one is missing (content-management.md:415).
     */
    private function translationFor(string $locale): ?TimelineItemTranslation
    {
        $translations = $this->translationsByLocale();

        return $translations[$locale] ?? $translations['en'] ?? null;
    }

    /**
     * @template T
     * @param  callable(TimelineItemTranslation): T  $extractor
     * @return array<string, T>
     */
    private function mapTranslations(callable $extractor): array
    {
        $map = [];
        foreach ($this->translationsByLocale() as $locale => $translation) {
            $map[$locale] = $extractor($translation);
        }

        return $map;
    }
}
