<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\Domain\ValueObjects\ReadingTime;

/**
 * The reading-time formula (database.md:360, content-management.md:472):
 * `ceil(word_count / 200)` over the content text.
 */
describe('ReadingTime', function (): void {
    it('computes ceil(word_count / 200) minutes', function (): void {
        expect(ReadingTime::fromText('')->toMinutes())->toBe(0)
            ->and(ReadingTime::fromText('one two three')->toMinutes())->toBe(1)
            ->and(ReadingTime::fromText(implode(' ', array_fill(0, 200, 'word')))->toMinutes())->toBe(1)
            ->and(ReadingTime::fromText(implode(' ', array_fill(0, 201, 'word')))->toMinutes())->toBe(2)
            ->and(ReadingTime::fromText(implode(' ', array_fill(0, 400, 'word')))->toMinutes())->toBe(2)
            ->and(ReadingTime::fromText(implode(' ', array_fill(0, 401, 'word')))->toMinutes())->toBe(3);
    });

    it('collapses whitespace runs when counting words', function (): void {
        expect(ReadingTime::fromText("  one \t two \n\n three  ")->toMinutes())->toBe(1);
    });

    it('rejects negative minutes', function (): void {
        expect(fn () => new ReadingTime(-1))
            ->toThrow(\InvalidArgumentException::class);
    });
});
