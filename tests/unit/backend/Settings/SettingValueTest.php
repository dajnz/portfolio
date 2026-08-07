<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Settings;

use App\Features\Settings\Domain\Enums\SettingType;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use InvalidArgumentException;

describe('SettingValue', function (): void {
    it('accepts values that conform to their type', function (): void {
        expect(new SettingValue(SettingType::Boolean, true))->not->toBeNull()
            ->and(new SettingValue(SettingType::Boolean, '0'))->not->toBeNull()
            ->and(new SettingValue(SettingType::Integer, 12))->not->toBeNull()
            ->and(new SettingValue(SettingType::Integer, '12'))->not->toBeNull()
            ->and(new SettingValue(SettingType::Json, ['en', 'ru']))->not->toBeNull()
            ->and(new SettingValue(SettingType::Json, '["en"]'))->not->toBeNull()
            ->and(new SettingValue(SettingType::String, 'smtp'))->not->toBeNull();
    });

    it('rejects values that do not conform to their type', function (): void {
        expect(fn () => new SettingValue(SettingType::Boolean, 'yes'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::Boolean, null))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::Integer, 12.5))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::Integer, '12.5'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::Integer, 'abc'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::Json, 'not-json'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SettingValue(SettingType::String, 123))->toThrow(InvalidArgumentException::class);
    });

    it('normalizes a null string input to an empty string', function (): void {
        $value = new SettingValue(SettingType::String, null);

        expect($value->value())->toBe('');
    });

    it('normalizes numeric strings to ints for integer values (Redis cache round-trip)', function (): void {
        $value = new SettingValue(SettingType::Integer, '12');

        expect($value->value())->toBe(12)
            ->and($value->toRaw())->toBe('12');
    });

    it('compares two instances by type and value', function (): void {
        $first = new SettingValue(SettingType::Integer, 12);
        $second = new SettingValue(SettingType::Integer, 12);
        $different = new SettingValue(SettingType::Integer, 24);
        $otherType = new SettingValue(SettingType::String, '12');

        expect($first->equals($second))->toBeTrue()
            ->and($first->equals($different))->toBeFalse()
            ->and($first->equals($otherType))->toBeFalse();
    });

    it('round-trips raw DB text through fromRaw and toRaw', function (): void {
        $boolean = SettingValue::fromRaw(SettingType::Boolean, '1');
        $integer = SettingValue::fromRaw(SettingType::Integer, '12');
        $json = SettingValue::fromRaw(SettingType::Json, '["en","ru"]');
        $string = SettingValue::fromRaw(SettingType::String, 'smtp');

        expect($boolean->value())->toBeTrue()
            ->and($boolean->toRaw())->toBe('1')
            ->and($integer->value())->toBe(12)
            ->and($integer->toRaw())->toBe('12')
            ->and($json->value())->toBe(['en', 'ru'])
            ->and($json->toRaw())->toBe('["en","ru"]')
            ->and($string->value())->toBe('smtp')
            ->and($string->toRaw())->toBe('smtp');
    });

    it('treats null raw text as an empty string value', function (): void {
        expect(SettingValue::fromRaw(SettingType::String, null)->value())->toBe('');
    });

    it('throws on unparseable raw text instead of silently coercing', function (): void {
        expect(fn () => SettingValue::fromRaw(SettingType::Integer, 'abc'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => SettingValue::fromRaw(SettingType::Boolean, 'maybe'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => SettingValue::fromRaw(SettingType::Json, '{nope'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => SettingValue::fromRaw(SettingType::Integer, null))->toThrow(InvalidArgumentException::class);
    });

    it('converts to the specific PHP data types', function (): void {
        expect((new SettingValue(SettingType::Boolean, '0'))->asBool())->toBeFalse()
            ->and((new SettingValue(SettingType::Boolean, true))->asBool())->toBeTrue()
            ->and((new SettingValue(SettingType::Integer, '24'))->asInt())->toBe(24)
            ->and((new SettingValue(SettingType::String, 'smtp'))->asString())->toBe('smtp')
            ->and((new SettingValue(SettingType::Json, '["en"]'))->asArray())->toBe(['en'])
            ->and((new SettingValue(SettingType::Json, ['en', 'ru']))->asArray())->toBe(['en', 'ru']);
    });

    it('rejects conversions that do not match the value type', function (): void {
        expect(fn () => (new SettingValue(SettingType::String, 'x'))->asBool())->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new SettingValue(SettingType::Integer, 12))->asArray())->toThrow(InvalidArgumentException::class);
    });
});
