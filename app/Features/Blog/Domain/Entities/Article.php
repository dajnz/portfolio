<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Entities;

use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\ValueObjects\Slug;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A blog article (database.md:330-364, content-management.md:191-274):
 * the unique kebab-case slug, the optional lead image
 * ({@see StoredPicture} — the RustFS path + per-locale alt labels),
 * the featured flag, the publication state ({@see PublishedState} —
 * `published_at` immutable after the first publish) and the aggregate
 * parts: the per-locale translations (title + short description +
 * Markdown content — required per active locale), the article-tag ids
 * of the `article_tag_pivot`, the tech-skill ids of the
 * `article_skill_pivot` and the bidirectional `related_articles`
 * pairs (stored as the ids of the related articles — the pair rows
 * are the repository's concern, database.md:453-463).
 *
 * Invariants are delegated to the embedded value objects: the slug
 * rules to {@see Slug}, the picture rules to {@see StoredPicture},
 * the per-locale field rules to {@see ArticleTranslation} and the
 * publication transitions to {@see PublishedState}. The aggregate
 * itself enforces the non-empty translation set and the related-ids
 * sanity (no self-reference).
 *
 * The entity is mutable: `publish()` guards the publication
 * invariants — the article must not be published yet and every stored
 * translation must carry the required fields (title + short
 * description) — `unpublish()` keeps the first-publish timestamp for
 * a possible re-publish. The embedded value objects stay immutable.
 *
 * Localized state is projected per property: `titleFor($locale)` etc.
 * resolve the requested locale with an `en` fallback
 * (content-management.md:415), the `*ByLocale()` maps expose every
 * stored locale for the admin LocaleTabs.
 */
final class Article extends BaseEntity
{
    /**
     * @param  ArticleTranslation[]  $translations
     * @param  int[]  $articleTagIds  Tag ids of the `article_tag_pivot`.
     * @param  int[]  $skillTagIds  Skill ids of the `article_skill_pivot`.
     * @param  int[]  $relatedArticleIds  The ids of the related articles (bidirectional pairs).
     */
    public function __construct(
        private ArticleId $id,
        private Slug $slug,
        private ?StoredPicture $leadImage,
        private bool $isFeatured,
        private PublishedState $published,
        private array $translations,
        private array $articleTagIds = [],
        private array $skillTagIds = [],
        private array $relatedArticleIds = [],
        private ?DateTimeImmutable $updatedAt = null,
    ) {
        if ($translations === []) {
            throw new InvalidArgumentException('Article must have at least one translation.');
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof ArticleTranslation) {
                throw new InvalidArgumentException('Article translations must be ArticleTranslation instances.');
            }
        }

        foreach ($relatedArticleIds as $relatedArticleId) {
            if ($relatedArticleId === $this->id->toValue()) {
                throw new InvalidArgumentException('An article cannot be related to itself.');
            }
        }
    }

    public function toId(): int
    {
        return $this->id->toValue();
    }

    public function toSlug(): string
    {
        return $this->slug->toValue();
    }

    public function toLeadImage(): ?StoredPicture
    {
        return $this->leadImage;
    }

    public function toIsFeatured(): bool
    {
        return $this->isFeatured;
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
     * The last save stamp of the base row — the `updated_at` column.
     * Read-only metadata: the app layer uses it for the cached-content
     * key (`article:html:{id}:{locale}:{updated_at}`,
     * content-management.md:479) and the SEO `dateModified`; the
     * domain never mutates it.
     */
    public function toUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return ArticleTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @return int[]
     */
    public function toArticleTagIds(): array
    {
        return $this->articleTagIds;
    }

    /**
     * @return int[]
     */
    public function toSkillTagIds(): array
    {
        return $this->skillTagIds;
    }

    /**
     * @return int[]
     */
    public function toRelatedArticleIds(): array
    {
        return $this->relatedArticleIds;
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

    public function titleFor(string $locale): string
    {
        return $this->translationFor($locale)?->toTitle() ?? '';
    }

    public function shortDescriptionFor(string $locale): string
    {
        return $this->translationFor($locale)?->toShortDescription() ?? '';
    }

    public function contentFor(string $locale): string
    {
        return $this->translationFor($locale)?->toContent() ?? '';
    }

    /**
     * The stored reading time of the locale's translation row
     * (recomputed on every save — content-management.md:472), with the
     * `en` fallback.
     */
    public function readingTimeFor(string $locale): int
    {
        return $this->translationFor($locale)?->toReadingTimeMinutes() ?? 0;
    }

    /**
     * Locale => title — every stored translation row (the admin
     * LocaleTabs render all of them, disabled locales included).
     *
     * @return array<string, string>
     */
    public function titlesByLocale(): array
    {
        return $this->mapTranslations(static fn (ArticleTranslation $translation): string => $translation->toTitle());
    }

    /**
     * @return array<string, string>
     */
    public function shortDescriptionsByLocale(): array
    {
        return $this->mapTranslations(static fn (ArticleTranslation $translation): string => $translation->toShortDescription());
    }

    /**
     * @return array<string, string>
     */
    public function contentsByLocale(): array
    {
        return $this->mapTranslations(static fn (ArticleTranslation $translation): string => $translation->toContent());
    }

    /**
     * Publishes the article: the publication invariants are asserted
     * first (every stored translation carries the required title +
     * short description AND actual Markdown content; the article is
     * not published yet) and the publication state is replaced with
     * the published one. `published_at` is set only on the FIRST
     * publish and immutable thereafter (database.md:341).
     */
    public function publish(DateTimeImmutable $now): void
    {
        foreach ($this->translations as $translation) {
            if ($translation->toTitle() === ''
                || $translation->toShortDescription() === ''
                || $translation->toContent() === ''
            ) {
                throw new InvalidArgumentException(
                    'A published article requires the title, short description and content in every stored locale.'
                );
            }
        }

        if ($this->published->toIsPublished()) {
            throw new InvalidArgumentException('Article is already published.');
        }

        $this->published = $this->published->publish($now);
    }

    /**
     * Unpublishes the article, preserving `published_at` — the value
     * is immutable after the first publish (database.md:341).
     */
    public function unpublish(): void
    {
        $this->published = $this->published->unpublish();
    }

    /**
     * @return array<string, ArticleTranslation>
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
    private function translationFor(string $locale): ?ArticleTranslation
    {
        $translations = $this->translationsByLocale();

        return $translations[$locale] ?? $translations['en'] ?? null;
    }

    /**
     * @template T
     * @param  callable(ArticleTranslation): T  $extractor
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
