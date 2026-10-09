<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\App\Services\BackupFilename;
use DateTimeImmutable;

describe('BackupFilename', function (): void {
    it('builds the plain create name without a key', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(BackupFilename::for($createdAt))->toBe('backup-2026-07-09-020000.zip');
    });

    it('appends the key id and the key name after a double dash', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(BackupFilename::for($createdAt, 3, 'rotated-2026'))
            ->toBe('backup-2026-07-09-020000-k3--rotated-2026.zip');
    });

    it('appends only the key id when no name is given', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(BackupFilename::for($createdAt, 3))->toBe('backup-2026-07-09-020000-k3.zip');
    });

    it('sanitizes the key name to filename-safe characters', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(BackupFilename::for($createdAt, 3, 'rotated key/2026:primary'))
            ->toBe('backup-2026-07-09-020000-k3--rotated-key-2026-primary.zip');
    });

    it('builds the DR import name with the imported key name', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(BackupFilename::forImport($createdAt, 3, 'disaster-key-a1b2c3d4'))
            ->toBe('backup-import-2026-07-09-020000-k3--disaster-key-a1b2c3d4.zip');
    });

    it('keeps the timestamp-second precision of the ADR-0018 convention', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:59');

        expect(BackupFilename::for($createdAt, 1, 'initial-key'))
            ->toBe('backup-2026-07-09-020059-k1--initial-key.zip');
    });
});
