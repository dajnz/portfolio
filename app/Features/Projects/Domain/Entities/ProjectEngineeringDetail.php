<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Projects\Domain\ValueObjects\EngineeringDetailIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use InvalidArgumentException;

/**
 * An engineering detail item of the project detail page
 * (database.md:236-249): an optional {@see EngineeringDetailIcon}
 * identifier from the curated `engineering` set (config/portfolio.php →
 * `icons.sets.engineering.icons`), its zero-based `sort_order`, and the
 * per-locale title/description rows
 * ({@see ProjectEngineeringDetailTranslation} — required per locale
 * when the item exists, content-management.md:645-646).
 *
 * Invariants: the Iconify `set:name` format is validated by
 * {@see EngineeringDetailIcon} when present; the position is a
 * non-negative {@see SortOrder}; the translations must cover at least
 * one locale with a title.
 */
final class ProjectEngineeringDetail extends BaseEntity
{
    /**
     * @param  ProjectEngineeringDetailTranslation[]  $translations
     */
    public function __construct(
        private int $id,
        private ?EngineeringDetailIcon $icon,
        private SortOrder $sortOrder,
        private array $translations,
    ) {
        if ($translations === []) {
            throw new InvalidArgumentException('Project engineering detail must have at least one translation.');
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof ProjectEngineeringDetailTranslation) {
                throw new InvalidArgumentException('Project engineering detail translations must be ProjectEngineeringDetailTranslation instances.');
            }
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toIcon(): ?string
    {
        return $this->icon?->toValue();
    }

    public function toSortOrder(): SortOrder
    {
        return $this->sortOrder;
    }

    /**
     * @return ProjectEngineeringDetailTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    /**
     * Title for the given locale, falling back to `en` (content-
     * management.md:418).
     */
    public function titleFor(string $locale): string
    {
        return $this->translationFor($locale)?->toTitle() ?? '';
    }

    /**
     * Description for the given locale, falling back to `en`.
     */
    public function descriptionFor(string $locale): string
    {
        return $this->translationFor($locale)?->toDescription() ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function titlesByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectEngineeringDetailTranslation $translation): string => $translation->toTitle());
    }

    /**
     * @return array<string, string>
     */
    public function descriptionsByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectEngineeringDetailTranslation $translation): string => $translation->toDescription());
    }

    /**
     * @return array<string, ProjectEngineeringDetailTranslation>
     */
    private function translationsByLocale(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->toLocale()] = $translation;
        }

        return $map;
    }

    private function translationFor(string $locale): ?ProjectEngineeringDetailTranslation
    {
        $translations = $this->translationsByLocale();

        return $translations[$locale] ?? $translations['en'] ?? null;
    }

    /**
     * @template T
     * @param  callable(ProjectEngineeringDetailTranslation): T  $extractor
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
