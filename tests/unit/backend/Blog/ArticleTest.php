<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Entities\ArticleTranslation;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @return ArticleTranslation
 */
function translation(): ArticleTranslation
{
    return new ArticleTranslation(
        locale: 'en',
        title: 'My Post',
        shortDescription: 'A short description.',
        content: 'The article body in markdown.',
    );
}

function translatedArticle(PublishedState $published): Article
{
    return new Article(
        id: new ArticleId(1),
        slug: new Slug('my-post'),
        leadImage: null,
        published: $published,
        translations: [
            new ArticleTranslation(
                locale: 'en',
                title: 'My Post',
                shortDescription: 'A short description.',
                content: 'The article body in markdown.',
            ),
            new ArticleTranslation(
                locale: 'ru',
                title: 'Мой пост',
                shortDescription: 'Краткое описание.',
                content: 'Тело статьи в markdown.',
            ),
        ],
    );
}

describe('Article', function (): void {
    it('publishes a fully translated article and sets published_at on the first publish', function (): void {
        $article = translatedArticle(PublishedState::unpublished());

        $article->publish(new DateTimeImmutable('2026-08-13 10:00:00'));

        expect($article->toIsPublished())->toBeTrue()
            ->and($article->toPublishedAt()?->format('Y-m-d H:i:s'))->toBe('2026-08-13 10:00:00');
    });

    it('rejects a translation missing the short description at construction', function (): void {
        // The per-locale field invariants live at the translation's
        // construction (the DB columns are NOT NULL) — the aggregate's
        // publish() check is the defensive backstop.
        expect(fn () => new Article(
            id: new ArticleId(1),
            slug: new Slug('my-post'),
            leadImage: null,
            published: PublishedState::unpublished(),
            translations: [
                new ArticleTranslation(
                    locale: 'en',
                    title: 'My Post',
                    shortDescription: '',
                    content: 'Some content',
                ),
            ],
        ))->toThrow(InvalidArgumentException::class, 'short description');
    });

    it('rejects a second publish', function (): void {
        $article = translatedArticle(PublishedState::published(new DateTimeImmutable('2026-08-13 10:00:00')));

        expect(fn () => $article->publish(new DateTimeImmutable('2026-08-14 10:00:00')))
            ->toThrow(InvalidArgumentException::class, 'already published');
    });

    it('keeps published_at after an unpublish and a re-publish', function (): void {
        $firstAt = new DateTimeImmutable('2026-08-13 10:00:00');
        $article = translatedArticle(PublishedState::unpublished());
        $article->publish($firstAt);

        $article->unpublish();
        $article->publish(new DateTimeImmutable('2026-08-14 10:00:00'));

        expect($article->toIsPublished())->toBeTrue()
            ->and($article->toPublishedAt())->toEqual($firstAt);
    });

    it('rejects an empty translation set', function (): void {
        expect(fn () => new Article(
            id: new ArticleId(1),
            slug: new Slug('my-post'),
            leadImage: null,
            published: PublishedState::unpublished(),
            translations: [],
        ))->toThrow(InvalidArgumentException::class, 'at least one translation');
    });

    it('rejects a self-relation', function (): void {
        expect(fn () => new Article(
            id: new ArticleId(5),
            slug: new Slug('my-post'),
            leadImage: null,
            published: PublishedState::unpublished(),
            translations: [translation()],
            relatedArticleIds: [5],
        ))->toThrow(InvalidArgumentException::class, 'related to itself');
    });

    it('resolves localized projections with the en fallback', function (): void {
        $article = translatedArticle(PublishedState::unpublished());

        expect($article->titleFor('en'))->toBe('My Post')
            ->and($article->titleFor('ru'))->toBe('Мой пост')
            ->and($article->titleFor('de'))->toBe('My Post')
            ->and($article->toTranslationLocales())->toBe(['en', 'ru']);
    });
});
