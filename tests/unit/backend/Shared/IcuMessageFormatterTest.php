<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\App\Services\IcuMessageFormatter;
use IntlException;

describe('IcuMessageFormatter', function (): void {
    it('resolves English plural forms', function (): void {
        $formatter = new IcuMessageFormatter();
        $pattern = '{minutes, plural, one {# minute} other {# minutes}}';

        expect($formatter->format($pattern, ['minutes' => 1], 'en'))->toBe('1 minute')
            ->and($formatter->format($pattern, ['minutes' => 3], 'en'))->toBe('3 minutes');
    });

    it('resolves Russian one/few/many plural forms', function (): void {
        $formatter = new IcuMessageFormatter();
        $pattern = '{minutes, plural, one {# минута чтения} few {# минуты чтения} many {# минут чтения} other {# минут чтения}}';

        expect($formatter->format($pattern, ['minutes' => 1], 'ru'))->toBe('1 минута чтения')
            ->and($formatter->format($pattern, ['minutes' => 2], 'ru'))->toBe('2 минуты чтения')
            ->and($formatter->format($pattern, ['minutes' => 5], 'ru'))->toBe('5 минут чтения')
            ->and($formatter->format($pattern, ['minutes' => 21], 'ru'))->toBe('21 минута чтения')
            ->and($formatter->format($pattern, ['minutes' => 42], 'ru'))->toBe('42 минуты чтения');
    });

    it('resolves select forms', function (): void {
        $formatter = new IcuMessageFormatter();
        $pattern = '{type, select, articles {the article} projects {the project} other {the item}}';

        expect($formatter->format($pattern, ['type' => 'articles'], 'en'))->toBe('the article')
            ->and($formatter->format($pattern, ['type' => 'projects'], 'en'))->toBe('the project')
            ->and($formatter->format($pattern, ['type' => 'unknown'], 'en'))->toBe('the item');
    });

    it('applies the plural rules of the given locale', function (): void {
        $formatter = new IcuMessageFormatter();
        $pattern = '{minutes, plural, one {# минута чтения} few {# минуты чтения} many {# минут чтения} other {# минут чтения}}';

        expect($formatter->format($pattern, ['minutes' => 2], 'en'))->toBe('2 минут чтения')
            ->and($formatter->format($pattern, ['minutes' => 2], 'ru'))->toBe('2 минуты чтения');
    });

    it('passes plain strings through unchanged', function (): void {
        $formatter = new IcuMessageFormatter();

        expect($formatter->format('Read more', [], 'en'))->toBe('Read more')
            ->and($formatter->format('Читать далее', [], 'ru'))->toBe('Читать далее');
    });

    it('keeps placeholders of missing arguments unresolved', function (): void {
        $formatter = new IcuMessageFormatter();

        expect($formatter->format('Hello {name}', [], 'en'))->toBe('Hello {name}');
    });

    it('defaults to the en locale', function (): void {
        $formatter = new IcuMessageFormatter();
        $pattern = '{count, plural, one {# item selected} other {# items selected}}';

        expect($formatter->format($pattern, ['count' => 2]))->toBe('2 items selected');
    });

    it('throws an IntlException for an invalid pattern', function (): void {
        $formatter = new IcuMessageFormatter();

        $formatter->format('{minutes, plural, one {# min read}', ['minutes' => 1], 'en');
    })->throws(IntlException::class);
});
