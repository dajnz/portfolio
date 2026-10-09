<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `backups` row entity (backup.md:132-159, database.md:567-587):
 * the lifecycle state machine — `pending → processing → completed`,
 * `pending | processing → failed`, plus `completed → processing` for
 * the restore of an existing backup (ADR-0018) — and the per-status shape
 * invariants (archive stamp on completion, failure time on fail,
 * neither while pending/processing, backup.yaml:55-67).
 */
describe('Backup', function (): void {
    it('exposes the stored fields of a pending backup', function (): void {
        $backup = new Backup(
            id: 7,
            filename: 'backup-2026-07-09-020000-k3.zip',
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Pending,
            encryptionKeyId: 3,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                null,
                null,
            ),
        );

        expect($backup->toId())->toBe(7)
            ->and($backup->toFilename())->toBe('backup-2026-07-09-020000-k3.zip')
            ->and($backup->toPath())->toBeNull()
            ->and($backup->toSizeBytes())->toBeNull()
            ->and($backup->toStatus())->toBe(BackupStatus::Pending)
            ->and($backup->toEncryptionKeyId())->toBe(3)
            ->and($backup->toRecordCounts())->toBeNull()
            ->and($backup->toAssetsCount())->toBeNull()
            ->and($backup->toAssetsSizeBytes())->toBeNull()
            ->and($backup->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-09 02:00:00'))
            ->and($backup->toCompletedAt())->toBeNull()
            ->and($backup->toDeletedAt())->toBeNull();
    });

    it('materializes a completed row via fromRow', function (): void {
        $backup = Backup::fromRow([
            'id' => 3,
            'filename' => 'backup-2026-07-09-020000-k3.zip',
            'path' => 'backups/backup-2026-07-09-020000-k3.zip',
            'size_bytes' => 52428800,
            'status' => 'completed',
            'encryption_key_id' => 3,
            'record_counts' => ['about_info' => 1, 'settings' => 14],
            'assets_count' => 2,
            'assets_size_bytes' => 512000,
            'created_at' => '2026-07-09 02:00:00',
            'completed_at' => '2026-07-09 02:02:00',
            'deleted_at' => null,
        ]);

        expect($backup->toId())->toBe(3)
            ->and($backup->toStatus())->toBe(BackupStatus::Completed)
            ->and($backup->toPath())->toBe('backups/backup-2026-07-09-020000-k3.zip')
            ->and($backup->toSizeBytes())->toBe(52428800)
            ->and($backup->toRecordCounts())->toBe(['about_info' => 1, 'settings' => 14])
            ->and($backup->toAssetsCount())->toBe(2)
            ->and($backup->toAssetsSizeBytes())->toBe(512000)
            ->and($backup->toCompletedAt())->toEqual(new DateTimeImmutable('2026-07-09 02:02:00'));
    });

    it('maps the pending-row NOT NULL placeholders back to null', function (): void {
        $backup = Backup::fromRow([
            'id' => 1,
            'filename' => 'backup-2026-07-09-020000-k1.zip',
            'path' => '',
            'size_bytes' => 0,
            'status' => 'pending',
            'encryption_key_id' => 1,
            'record_counts' => null,
            'assets_count' => null,
            'assets_size_bytes' => null,
            'created_at' => '2026-07-09 02:00:00',
            'completed_at' => null,
            'deleted_at' => null,
        ]);

        expect($backup->toPath())->toBeNull()
            ->and($backup->toSizeBytes())->toBeNull()
            ->and($backup->toStatus())->toBe(BackupStatus::Pending);
    });

    it('materializes a failed restore row that keeps the original archive stamp', function (): void {
        $backup = Backup::fromRow([
            'id' => 3,
            'filename' => 'backup-2026-07-09-020000-k3.zip',
            'path' => 'backups/backup-2026-07-09-020000-k3.zip',
            'size_bytes' => 52428800,
            'status' => 'failed',
            'encryption_key_id' => 3,
            'record_counts' => ['about_info' => 1, 'settings' => 14],
            'assets_count' => 2,
            'assets_size_bytes' => 512000,
            'created_at' => '2026-07-09 02:00:00',
            'completed_at' => '2026-07-09 02:05:00',
            'deleted_at' => null,
        ]);

        expect($backup->toStatus())->toBe(BackupStatus::Failed)
            ->and($backup->toPath())->toBe('backups/backup-2026-07-09-020000-k3.zip')
            ->and($backup->toCompletedAt())->toEqual(new DateTimeImmutable('2026-07-09 02:05:00'));
    });

    it('only allows downloads of completed backups and deletes outside processing', function (): void {
        $completed = completedBackup();

        expect($completed->canDownload())->toBeTrue()
            ->and($completed->canDelete())->toBeTrue();

        $processing = completedBackup();
        $processing->startProcessing();

        expect($processing->canDownload())->toBeFalse()
            ->and($processing->canDelete())->toBeFalse();

        foreach ([BackupStatus::Pending, BackupStatus::Failed] as $status) {
            $backup = new Backup(
                id: 1,
                filename: 'backup-2026-07-09-020000-k1.zip',
                path: null,
                summary: new BackupContentSummary(null, null, null, null),
                status: $status,
                encryptionKeyId: null,
                timings: new BackupTimings(
                    new DateTimeImmutable('2026-07-09 02:00:00'),
                    $status === BackupStatus::Failed ? new DateTimeImmutable('2026-07-09 02:00:05') : null,
                    null,
                ),
            );

            expect($backup->canDownload())->toBeFalse()
                ->and($backup->canDelete())->toBeTrue();
        }
    });

    it('transitions a pending backup through processing to completed', function (): void {
        $backup = pendingBackup();

        $backup->startProcessing();

        expect($backup->toStatus())->toBe(BackupStatus::Processing)
            ->and($backup->toCompletedAt())->toBeNull();

        $backup->complete('backups/backup-2026-07-09-020000-k3.zip', sampleSummary());

        expect($backup->toStatus())->toBe(BackupStatus::Completed)
            ->and($backup->toPath())->toBe('backups/backup-2026-07-09-020000-k3.zip')
            ->and($backup->toSizeBytes())->toBe(52428800)
            ->and($backup->toRecordCounts())->toBe(['about_info' => 1, 'settings' => 14])
            ->and($backup->toAssetsCount())->toBe(2)
            ->and($backup->toAssetsSizeBytes())->toBe(512000)
            ->and($backup->toCompletedAt())->not->toBeNull();
    });

    it('re-processes a completed backup for restore and clears its completion stamp', function (): void {
        $backup = completedBackup();

        $backup->startProcessing();

        expect($backup->toStatus())->toBe(BackupStatus::Processing)
            ->and($backup->toCompletedAt())->toBeNull()
            ->and($backup->canDownload())->toBeFalse()
            ->and($backup->canDelete())->toBeFalse();
    });

    it('fails a pending or processing backup', function (): void {
        foreach ([BackupStatus::Pending, BackupStatus::Processing] as $status) {
            $backup = pendingBackup();

            if ($status === BackupStatus::Processing) {
                $backup->startProcessing();
            }

            $backup->fail();

            expect($backup->toStatus())->toBe(BackupStatus::Failed)
                ->and($backup->toCompletedAt())->not->toBeNull()
                ->and($backup->toPath())->toBeNull();
        }
    });

    it('rejects a second startProcessing while already processing', function (): void {
        $backup = pendingBackup();
        $backup->startProcessing();

        expect(fn () => $backup->startProcessing())->toThrow(
            InvalidArgumentException::class,
            'Backup is already processing.',
        );
    });

    it('rejects starting processing from the failed status', function (): void {
        $backup = pendingBackup();
        $backup->fail();

        expect(fn () => $backup->startProcessing())->toThrow(
            InvalidArgumentException::class,
            'Backup 3 cannot start processing from the failed status.',
        );
    });

    it('rejects completing a backup that is not processing', function (): void {
        $backup = pendingBackup();

        expect(fn () => $backup->complete('backups/backup-2026-07-09-020000-k3.zip', sampleSummary()))->toThrow(
            InvalidArgumentException::class,
            'Backup 3 cannot complete without being processed first.',
        );
    });

    it('rejects completing with an empty content summary', function (): void {
        $backup = pendingBackup();
        $backup->startProcessing();

        expect(fn () => $backup->complete(
            'backups/backup-2026-07-09-020000-k3.zip',
            new BackupContentSummary(null, null, null, null),
        ))->toThrow(
            InvalidArgumentException::class,
            'Backup completion requires a fully populated content summary.',
        );
    });

    it('rejects failing a completed backup', function (): void {
        $backup = completedBackup();

        expect(fn () => $backup->fail())->toThrow(
            InvalidArgumentException::class,
            'Backup 3 cannot fail from the completed status.',
        );
    });

    it('rejects a too-long filename or path', function (): void {
        expect(fn () => new Backup(
            id: 1,
            filename: str_repeat('f', Backup::MAX_FILENAME_LENGTH + 1),
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Pending,
            encryptionKeyId: null,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                null,
                null,
            ),
        ))->toThrow(InvalidArgumentException::class, 'Backup filename must be a non-empty string of at most 255 characters.');

        expect(fn () => new Backup(
            id: 1,
            filename: 'backup-2026-07-09-020000-k1.zip',
            path: str_repeat('p', Backup::MAX_PATH_LENGTH + 1),
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Pending,
            encryptionKeyId: null,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                null,
                null,
            ),
        ))->toThrow(InvalidArgumentException::class, 'Backup path must be a non-empty string of at most 500 characters.');
    });

    it('enforces the per-status row shape', function (): void {
        expect(fn () => new Backup(
            id: 1,
            filename: 'backup-2026-07-09-020000-k1.zip',
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Completed,
            encryptionKeyId: null,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                new DateTimeImmutable('2026-07-09 02:02:00'),
                null,
            ),
        ))->toThrow(InvalidArgumentException::class, 'A completed backup must carry its archive stamp and completion time.');

        expect(fn () => new Backup(
            id: 1,
            filename: 'backup-2026-07-09-020000-k1.zip',
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Failed,
            encryptionKeyId: null,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                null,
                null,
            ),
        ))->toThrow(InvalidArgumentException::class, 'A failed backup must carry its failure time.');

        expect(fn () => new Backup(
            id: 1,
            filename: 'backup-2026-07-09-020000-k1.zip',
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Pending,
            encryptionKeyId: null,
            timings: new BackupTimings(
                new DateTimeImmutable('2026-07-09 02:00:00'),
                new DateTimeImmutable('2026-07-09 02:02:00'),
                null,
            ),
        ))->toThrow(InvalidArgumentException::class, 'A pending or processing backup must not carry a completion time.');
    });
});

function pendingBackup(): Backup
{
    return new Backup(
        id: 3,
        filename: 'backup-2026-07-09-020000-k3.zip',
        path: null,
        summary: new BackupContentSummary(null, null, null, null),
        status: BackupStatus::Pending,
        encryptionKeyId: 3,
        timings: new BackupTimings(
            new DateTimeImmutable('2026-07-09 02:00:00'),
            null,
            null,
        ),
    );
}

function completedBackup(): Backup
{
    $backup = pendingBackup();
    $backup->startProcessing();
    $backup->complete('backups/backup-2026-07-09-020000-k3.zip', sampleSummary());

    return $backup;
}

function sampleSummary(): BackupContentSummary
{
    return new BackupContentSummary(
        sizeBytes: 52428800,
        assetsCount: 2,
        assetsSizeBytes: 512000,
        recordCounts: ['about_info' => 1, 'settings' => 14],
    );
}
