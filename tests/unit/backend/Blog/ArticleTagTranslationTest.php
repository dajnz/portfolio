<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\Domain\Entities\ArticleTagTranslation;
use InvalidArgumentException;

describe('ArticleTagTranslation', function (): void {
    it('rejects a blank name', function (): void {
        expect(fn () => new ArticleTagTranslation(locale: 'en', name: ''))
            ->toThrow(InvalidArgumentException::class, 'must not be empty');
    });

    it('rejects a name longer than 100 characters', function (): void {
        expect(fn () => new ArticleTagTranslation(locale: 'en', name: str_repeat('a', 101)))
            ->toThrow(InvalidArgumentException::class, '100 characters');
    });
});
