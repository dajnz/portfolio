<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `backups` timestamps VO (database.md:582-585): the creation
 * moment plus the nullable completion/failure and deletion stamps —
 * neither may precede the creation time. The entity's state
 * transitions swap immutable instances via `withCompletedAt()` /
 * `withoutCompletedAt()` (ADR-0018).
 */
describe('BackupTimings', function (): void {
    it('exposes the timestamps', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');
        $completedAt = new DateTimeImmutable('2026-07-09 02:02:00');
        $deletedAt = new DateTimeImmutable('2026-07-10 10:00:00');

        $timings = new BackupTimings($createdAt, $completedAt, $deletedAt);

        expect($timings->toCreatedAt())->toEqual($createdAt)
            ->and($timings->toCompletedAt())->toEqual($completedAt)
            ->and($timings->toDeletedAt())->toEqual($deletedAt);
    });

    it('allows null completion and deletion stamps', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        $timings = new BackupTimings($createdAt, null, null);

        expect($timings->toCompletedAt())->toBeNull()
            ->and($timings->toDeletedAt())->toBeNull();
    });

    it('stamps and clears the completion time immutably', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');
        $completedAt = new DateTimeImmutable('2026-07-09 02:02:00');
        $timings = new BackupTimings($createdAt, null, null);

        $stamped = $timings->withCompletedAt($completedAt);

        expect($stamped->toCompletedAt())->toEqual($completedAt)
            ->and($timings->toCompletedAt())->toBeNull();

        expect($stamped->withoutCompletedAt()->toCompletedAt())->toBeNull()
            ->and($stamped->toDeletedAt())->toBeNull()
            ->and($stamped->toCreatedAt())->toEqual($createdAt);
    });

    it('rejects a completion or deletion stamp before the creation time', function (): void {
        $createdAt = new DateTimeImmutable('2026-07-09 02:00:00');

        expect(fn () => new BackupTimings(
            $createdAt,
            new DateTimeImmutable('2026-07-09 01:59:59'),
            null,
        ))->toThrow(InvalidArgumentException::class, 'Backup completion time must not precede its creation time.');

        expect(fn () => new BackupTimings(
            $createdAt,
            null,
            new DateTimeImmutable('2026-07-08 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Backup deletion time must not precede its creation time.');
    });
});
