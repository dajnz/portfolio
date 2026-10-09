<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Entities;

use App\Features\Projects\Domain\ValueObjects\FeaturedState;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Projects\Domain\ValueObjects\ProjectLinks;
use App\Features\Projects\Domain\ValueObjects\ProjectPictures;
use App\Features\Projects\Domain\ValueObjects\ProjectSlugs;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\BlogArticleId;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A portfolio project: the slug history ({@see ProjectSlugs} — the
 * current kebab-case slug and the recorded old ones), the picture set
 * ({@see ProjectPictures} — the optional preview picture and the
 * screenshot gallery), the URL pair ({@see ProjectLinks}), the
 * application-level tech-writeup article reference ({@see BlogArticleId}
 * — no FK; the linked article may live or die independently), the
 * featured state ({@see FeaturedState}) with the zero-indexed
 * `featured_sort_order`, the publication state ({@see PublishedState})
 * with the immutable `published_at`, the persisted `tech_tags_count`,
 * and the aggregate parts — per-locale translations, the tech-tag
 * skills of the `project_tech_tags` pivot and the engineering detail
 * items.
 *
 * Invariants are delegated to the embedded entities and value objects:
 * the slug rules to {@see ProjectSlugs}/{@see \App\Features\Shared\Domain\ValueObjects\Slug},
 * the picture rules to {@see ProjectPictures}/{@see ProjectScreenshot}/
 * {@see StoredPicture}, the link rules to {@see ProjectLinks}, the
 * per-locale field rules to {@see ProjectTranslation}, the detail
 * items to {@see ProjectEngineeringDetail} and the tag names to
 * {@see TechSkillTag}. The aggregate itself enforces the non-empty
 * translation set and the aggregate-part membership.
 *
 * The entity is mutable: every mutation checks the affected invariants
 * first. `publish()` guards the publication invariants — the project
 * must not be published yet and every stored translation must carry
 * the required fields (title + brief description) — `unpublish()`
 * keeps the first-publish timestamp for a possible re-publish. The
 * embedded value objects stay immutable.
 *
 * Localized state is projected per property: `titleFor($locale)` etc.
 * resolve the requested locale with an `en` fallback, the `*ByLocale()`
 * maps expose every stored locale for the admin LocaleTabs, and the
 * screenshot / engineering-detail collections stay accessible for the
 * form's merged editors.
 */
final class Project extends BaseEntity
{
    /**
     * @param  ProjectTranslation[]  $translations
     * @param  TechSkillTag[]  $techTags  Skill tags from the `project_tech_tags` pivot.
     * @param  ProjectEngineeringDetail[]  $engineeringDetails
     */
    public function __construct(
        private ProjectId $id,
        private ProjectSlugs $slugs,
        private ProjectPictures $pictures,
        private ProjectLinks $links,
        private ?BlogArticleId $techWriteupArticleId,
        private FeaturedState $featured,
        private PublishedState $published,
        private array $translations,
        private array $techTags = [],
        private array $engineeringDetails = [],
    ) {
        if ($translations === []) {
            throw new InvalidArgumentException('Project must have at least one translation.');
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof ProjectTranslation) {
                throw new InvalidArgumentException('Project translations must be ProjectTranslation instances.');
            }
        }

        foreach ($engineeringDetails as $engineeringDetail) {
            if (! $engineeringDetail instanceof ProjectEngineeringDetail) {
                throw new InvalidArgumentException('Project engineering details must be ProjectEngineeringDetail instances.');
            }
        }
    }

    public function toId(): int
    {
        return $this->id->toValue();
    }

    public function toSlug(): string
    {
        return $this->slugs->toCurrentSlug();
    }

    public function toSlugs(): ProjectSlugs
    {
        return $this->slugs;
    }

    public function toPreviewPicture(): ?StoredPicture
    {
        return $this->pictures->toPreviewPicture();
    }

    /**
     * @return ProjectScreenshot[]
     */
    public function toScreenshots(): array
    {
        return $this->pictures->toScreenshots();
    }

    public function toPictures(): ProjectPictures
    {
        return $this->pictures;
    }

    public function toSourceCodeUrl(): ?string
    {
        return $this->links->toSourceCodeUrl();
    }

    public function toLiveDemoUrl(): ?string
    {
        return $this->links->toLiveDemoUrl();
    }

    public function toLinks(): ProjectLinks
    {
        return $this->links;
    }

    public function toTechWriteupArticleId(): ?int
    {
        return $this->techWriteupArticleId?->toValue();
    }

    public function toIsFeatured(): bool
    {
        return $this->featured->toIsFeatured();
    }

    public function toFeaturedSortOrder(): ?int
    {
        return $this->featured->toFeaturedSortOrder();
    }

    public function toFeaturedState(): FeaturedState
    {
        return $this->featured;
    }

    public function toIsPublished(): bool
    {
        return $this->published->toIsPublished();
    }

    public function toPublishedAt(): ?DateTimeImmutable
    {
        return $this->published->toPublishedAt();
    }

    public function toPublishedState(): PublishedState
    {
        return $this->published;
    }

    /**
     * @return ProjectTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @return TechSkillTag[]
     */
    public function toTechTags(): array
    {
        return $this->techTags;
    }

    /**
     * @return ProjectEngineeringDetail[]
     */
    public function toEngineeringDetails(): array
    {
        return $this->engineeringDetails;
    }

    /**
     * The locales with a stored translation row — the exact set the
     * persistence layer reconciles against the active locales (rows of
     * disabled locales are preserved untouched).
     *
     * @return string[]
     */
    public function toTranslationLocales(): array
    {
        return array_keys($this->translationsByLocale());
    }

    public function titleFor(string $locale): string
    {
        return $this->translationFor($locale)?->toTitle() ?? '';
    }

    public function briefDescriptionFor(string $locale): string
    {
        return $this->translationFor($locale)?->toBriefDescription() ?? '';
    }

    public function fullDescriptionFor(string $locale): ?string
    {
        return $this->translationFor($locale)?->toFullDescription();
    }

    public function problemFor(string $locale): ?string
    {
        return $this->translationFor($locale)?->toProblem();
    }

    public function solutionFor(string $locale): ?string
    {
        return $this->translationFor($locale)?->toSolution();
    }

    /**
     * Locale => title — every stored translation row (the admin
     * LocaleTabs render all of them, disabled locales included).
     *
     * @return array<string, string>
     */
    public function titlesByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectTranslation $translation): string => $translation->toTitle());
    }

    /**
     * @return array<string, string>
     */
    public function briefDescriptionsByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectTranslation $translation): string => $translation->toBriefDescription());
    }

    /**
     * @return array<string, string|null>
     */
    public function fullDescriptionsByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectTranslation $translation): ?string => $translation->toFullDescription());
    }

    /**
     * @return array<string, string|null>
     */
    public function problemsByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectTranslation $translation): ?string => $translation->toProblem());
    }

    /**
     * @return array<string, string|null>
     */
    public function solutionsByLocale(): array
    {
        return $this->mapTranslations(static fn (ProjectTranslation $translation): ?string => $translation->toSolution());
    }

    /**
     * Publishes the project: the publication invariants are asserted
     * first (every stored translation carries the required title +
     * brief description; the project is not published yet) and the
     * publication state is replaced with the published one.
     * `published_at` is set only on the FIRST publish and immutable
     * thereafter.
     */
    public function publish(DateTimeImmutable $now): void
    {
        foreach ($this->translations as $translation) {
            if ($translation->toTitle() === '' || $translation->toBriefDescription() === '') {
                throw new InvalidArgumentException(
                    'A published project requires the title and brief description in every stored locale.'
                );
            }
        }

        if ($this->published->toIsPublished()) {
            throw new InvalidArgumentException('Project is already published.');
        }

        $this->published = $this->published->publish($now);
    }

    /**
     * Unpublishes the project, preserving `published_at` — the value
     * is immutable after the first publish.
     */
    public function unpublish(): void
    {
        $this->published = $this->published->unpublish();
    }

    /**
     * @return array<string, ProjectTranslation>
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
     * requested one is missing.
     */
    private function translationFor(string $locale): ?ProjectTranslation
    {
        $translations = $this->translationsByLocale();

        return $translations[$locale] ?? $translations['en'] ?? null;
    }

    /**
     * @template T
     * @param  callable(ProjectTranslation): T  $extractor
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
