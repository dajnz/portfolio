<?php

declare(strict_types=1);

use App\Features\Auth\Domain\ValueObjects\RecoveryCode;

describe('RecoveryCode', function (): void {
    it('generates codes in XXXX-XXXX-XXXX format from the unambiguous alphabet', function (): void {
        $code = RecoveryCode::generate();

        expect($code->toDisplayCode())->toMatch('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/')
            ->and($code->isUsed())->toBeFalse();
    });

    it('normalizes codes to uppercase alphanumeric without separators', function (): void {
        expect(RecoveryCode::normalize('abcd-efgh-ijkl'))->toBe('ABCDEFGHIJKL')
            ->and(RecoveryCode::normalize('ABCD EFGH IJKL'))->toBe('ABCDEFGHIJKL')
            ->and(RecoveryCode::normalize('abc'))->toBe('ABC');
    });

    it('matches the normalized input against its hash', function (): void {
        $code = RecoveryCode::generate();
        $display = $code->toDisplayCode();

        expect($code->matches(RecoveryCode::normalize($display)))->toBeTrue()
            ->and($code->matches(RecoveryCode::normalize(strtolower($display))))->toBeTrue()
            ->and($code->matches('ZZZZZZZZZZZZ'))->toBeFalse();
    });

    it('round-trips through the stored shape', function (): void {
        $code = RecoveryCode::generate();
        $stored = $code->toStored();

        expect($stored)->toHaveKeys(['hash', 'used'])
            ->and($stored['used'])->toBeFalse()
            ->and($stored['hash'])->not->toBe($code->toDisplayCode())
            ->and(RecoveryCode::fromStored($stored)->toStored())->toBe($stored);
    });

    it('marks a code as used', function (): void {
        $used = RecoveryCode::generate()->markUsed();

        expect($used->isUsed())->toBeTrue()
            ->and($used->toHash())->toBe($used->toHash());
    });

    it('rejects malformed stored entries', function (): void {
        expect(fn () => RecoveryCode::fromStored([]))->toThrow(InvalidArgumentException::class);
        expect(fn () => RecoveryCode::fromStored(['hash' => 'x']))->toThrow(InvalidArgumentException::class);
        expect(fn () => RecoveryCode::fromStored(['hash' => 'x', 'used' => 'yes']))->toThrow(InvalidArgumentException::class);
    });

    it('does not expose the plaintext for stored codes', function (): void {
        $stored = RecoveryCode::fromStored(['hash' => 'bcrypt', 'used' => true]);

        expect(fn () => $stored->toDisplayCode())->toThrow(LogicException::class);
    });
});
