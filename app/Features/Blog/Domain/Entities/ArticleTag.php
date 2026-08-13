<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\Slug;
use InvalidArgumentException;

/**
 * An article tag (database.md:366-384): the unique kebab-case slug
 * and the per-locale names (required per ACTIVE locale, content-
 * management.md:690-695). The tag↔skill name uniqueness is probed by
 * the tag repository (the case-insensitive cross-table check runs on
 * the article-tag side only — content-management.md:595,695) and
 * surfaced by the store/update use cases.
 */
final class ArticleTag extends BaseEntity
{
    /**
     * @param  ArticleTagTranslation[]  $translations
     */
    public function __construct(
        private int $id,
        private Slug $slug,
        private array $translations,
    ) {
        if ($translations === []) {
            throw new InvalidArgumentException('Article tag must have at least one translation.');
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof ArticleTagTranslation) {
                throw new InvalidArgumentException('Article tag translations must be ArticleTagTranslation instances.');
            }
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    /**
     * A copy of the tag with the persisted id (the store/update use
     * cases return the created/updated entity to the API surface).
     */
    public function withId(int $id): self
    {
        return new self($id, $this->slug, $this->translations);
    }

    public function toSlug(): string
    {
        return $this->slug->toValue();
    }

    /**
     * @return ArticleTagTranslation[]
     */
    public function toTranslations(): array
    {
        return $this->translations;
    }

    /**
     * The name for the given locale, falling back to `en`
     * (content-management.md:415).
     */
    public function nameFor(string $locale): string
    {
        return $this->translationFor($locale)?->toName() ?? '';
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
        return array_keys($this->namesByLocale());
    }

    /**
     * Locale => name — every stored translation row (the admin
     * LocaleTabs render all of them).
     *
     * @return array<string, string>
     */
    public function namesByLocale(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->toLocale()] = $translation->toName();
        }

        return $map;
    }

    private function translationFor(string $locale): ?ArticleTagTranslation
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
