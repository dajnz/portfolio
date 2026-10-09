<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `backup_encryption_keys` row entity (database.md:550-565,
 * backup.md:488-499): the raw key is a 44-character base64 encoding
 * of 32 random bytes (the `backup:generate-key` format), the name
 * fits the varchar(100) column, and `activate()`/`deactivate()`
 * model the rotation the generation use case performs.
 */
describe('EncryptionKey', function (): void {
    it('exposes the stored fields', function (): void {
        $rawKey = base64_encode(random_bytes(32));

        $key = new EncryptionKey(
            id: 3,
            name: 'initial-key',
            keyValue: $rawKey,
            isActive: true,
            createdAt: new DateTimeImmutable('2026-03-15 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-07-01 09:00:00'),
        );

        expect($key->toId())->toBe(3)
            ->and($key->toName())->toBe('initial-key')
            ->and($key->toKeyValue())->toBe($rawKey)
            ->and($key->isActive())->toBeTrue()
            ->and($key->toCreatedAt())->toEqual(new DateTimeImmutable('2026-03-15 10:00:00'))
            ->and($key->toUpdatedAt())->toEqual(new DateTimeImmutable('2026-07-01 09:00:00'));
    });

    it('materializes a stored row via fromRow', function (): void {
        $key = EncryptionKey::fromRow([
            'id' => 2,
            'name' => 'rotated-2026',
            'key_value' => base64_encode(random_bytes(32)),
            'is_active' => false,
            'created_at' => '2026-07-01 09:00:00',
            'updated_at' => '2026-07-01 09:00:00',
        ]);

        expect($key->toId())->toBe(2)
            ->and($key->toName())->toBe('rotated-2026')
            ->and($key->isActive())->toBeFalse()
            ->and(strlen($key->toKeyValue()))->toBe(EncryptionKey::RAW_KEY_LENGTH);
    });

    it('activates and deactivates the key', function (): void {
        $key = new EncryptionKey(
            id: 1,
            name: 'initial-key',
            keyValue: base64_encode(random_bytes(32)),
            isActive: false,
            createdAt: new DateTimeImmutable('2026-03-15 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-03-15 10:00:00'),
        );

        $key->activate();

        expect($key->isActive())->toBeTrue();

        $key->deactivate();

        expect($key->isActive())->toBeFalse();
    });

    it('names DR-imported keys deterministically disaster-key-{hash}', function (): void {
        $rawKey = base64_encode(random_bytes(32));
        $createdAt = new DateTimeImmutable('2026-08-24 10:00:00');

        $key = EncryptionKey::forImport($rawKey, $createdAt);
        $expectedName = 'disaster-key-'.substr(hash('sha256', $rawKey), 0, 8);

        expect($key->toName())->toBe($expectedName)
            ->and($key->isActive())->toBeFalse()
            ->and($key->toId())->toBe(0)
            ->and(strlen($key->toName()))->toBe(21);

        // The same raw key always yields the same name — no timestamp or
        // randomness in it (ADR-0019): the reuse check relies on the stable
        // mapping, and re-imports of the same key never collide.
        expect(EncryptionKey::forImport($rawKey, new DateTimeImmutable('2026-08-25 09:00:00'))->toName())
            ->toBe($expectedName);
    });

    it('rejects an empty or too-long name', function (): void {
        expect(fn () => new EncryptionKey(
            id: 1,
            name: '',
            keyValue: base64_encode(random_bytes(32)),
            isActive: false,
            createdAt: new DateTimeImmutable('2026-03-15 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-03-15 10:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Encryption key name must be a non-empty string of at most 100 characters.');

        expect(fn () => new EncryptionKey(
            id: 1,
            name: str_repeat('n', EncryptionKey::MAX_NAME_LENGTH + 1),
            keyValue: base64_encode(random_bytes(32)),
            isActive: false,
            createdAt: new DateTimeImmutable('2026-03-15 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-03-15 10:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Encryption key name must be a non-empty string of at most 100 characters.');
    });

    it('rejects key values that are not base64 of 32 bytes', function (): void {
        $rejects = function (string $value): void {
            expect(fn () => new EncryptionKey(
                id: 1,
                name: 'initial-key',
                keyValue: $value,
                isActive: false,
                createdAt: new DateTimeImmutable('2026-03-15 10:00:00'),
                updatedAt: new DateTimeImmutable('2026-03-15 10:00:00'),
            ))->toThrow(
                InvalidArgumentException::class,
                'Encryption key value must be a 44-character base64 encoding of 32 random bytes.',
            );
        };

        $rejects('too-short');
        $rejects(base64_encode(random_bytes(31)));
        $rejects(base64_encode(random_bytes(33)));
        $rejects('not*base64!value*with*wrong*chars*and*length*over*44*chars*here');
    });
});
