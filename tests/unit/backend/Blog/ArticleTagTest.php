<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\Domain\Entities\ArticleTag;
use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use App\Features\Shared\Domain\ValueObjects\Slug;
use InvalidArgumentException;

describe('ArticleTag', function (): void {
    it('rejects an empty translation set', function (): void {
        expect(fn () => new ArticleTag(id: 1, slug: new Slug('php'), translations: []))
            ->toThrow(InvalidArgumentException::class, 'at least one translation');
    });

    it('rejects a blank name', function (): void {
        expect(fn () => new ArticleTagTranslation(locale: 'en', name: ''))
            ->toThrow(InvalidArgumentException::class, 'must not be empty');
    });

    it('rejects a name longer than 100 characters', function (): void {
        expect(fn () => new ArticleTagTranslation(locale: 'en', name: str_repeat('a', 101)))
            ->toThrow(InvalidArgumentException::class, '100 characters');
    });

    it('resolves the name per locale with the en fallback', function (): void {
        $tag = new ArticleTag(
            id: 1,
            slug: new Slug('php'),
            translations: [
                new ArticleTagTranslation(locale: 'en', name: 'PHP'),
                new ArticleTagTranslation(locale: 'ru', name: 'ПХП'),
            ],
        );

        expect($tag->nameFor('en'))->toBe('PHP')
            ->and($tag->nameFor('ru'))->toBe('ПХП')
            ->and($tag->nameFor('de'))->toBe('PHP')
            ->and($tag->namesByLocale())->toBe(['en' => 'PHP', 'ru' => 'ПХП']);
    });
});
