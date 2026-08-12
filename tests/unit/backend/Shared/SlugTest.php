<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\Domain\ValueObjects\Slug;
use InvalidArgumentException;

/**
 * The shared slug VO (content-management.md:424-464): the kebab-case
 * invariant and the EN/RU slug generation (Cyrillic transliterated to
 * latin — the A3 auto-generation works for Russian titles too).
 */
describe('Slug', function (): void {
    it('accepts valid kebab-case values', function (): void {
        expect((new Slug('my-post'))->toValue())->toBe('my-post')
            ->and((new Slug('my-post-2'))->toValue())->toBe('my-post-2')
            ->and((new Slug('a1-b2-c3'))->toValue())->toBe('a1-b2-c3');
    });

    it('rejects empty, uppercase and malformed values', function (string $value): void {
        expect(fn () => new Slug($value))->toThrow(InvalidArgumentException::class);
    })->with([
        '',
        'My-Post',
        'my_post',
        'my post',
        'my--post',
        '-my-post',
        'my-post-',
        'my.post',
    ]);

    it('slugifies English strings', function (): void {
        expect(Slug::generate('Payments Gateway'))->toBe('payments-gateway')
            ->and(Slug::generate('  Hello, World!  '))->toBe('hello-world')
            ->and(Slug::generate('API v2 release'))->toBe('api-v2-release')
            ->and(Slug::generate('Naïve café'))->toBe('naive-cafe');
    });

    it('transliterates Russian strings to latin', function (): void {
        expect(Slug::generate('Платёжный шлюз'))->toBe('platyozhnyy-shlyuz')
            ->and(Slug::generate('Проект №1 — обзор'))->toBe('proekt-1-obzor')
            ->and(Slug::generate('Ёж и щука'))->toBe('yozh-i-schuka');
    });

    it('returns an empty string when nothing usable remains', function (): void {
        expect(Slug::generate('!!!'))->toBe('')
            ->and(Slug::generate('   '))->toBe('');
    });
});
