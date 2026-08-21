<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use InvalidArgumentException;

/**
 * The client identifiers VO (B289): the session id, the anonymized IP
 * hash and the User-Agent — the validation moved out of the event
 * base.
 */
describe('ClientIds', function (): void {
    it('exposes its values', function (): void {
        $ids = new ClientIds('s-1', 'ip-hash', 'Mozilla/5.0');

        expect($ids->toSessionId())->toBe('s-1')
            ->and($ids->toIpAddress())->toBe('ip-hash')
            ->and($ids->toUserAgent())->toBe('Mozilla/5.0');
    });

    it('accepts missing ip and user agent', function (): void {
        $ids = new ClientIds('s-1', null, null);

        expect($ids->toIpAddress())->toBeNull()
            ->and($ids->toUserAgent())->toBeNull();
    });

    it('rejects an empty session id', function (): void {
        expect(fn () => new ClientIds('', null, null))
            ->toThrow(InvalidArgumentException::class, 'Session id must not be empty.');
    });

    it('rejects a session id longer than 255 characters', function (): void {
        expect(fn () => new ClientIds(str_repeat('s', 256), null, null))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 255 characters');
    });

    it('rejects an ip hash longer than 64 characters', function (): void {
        expect(fn () => new ClientIds('s-1', str_repeat('ab', 33), null))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 64 characters');
    });
});
