<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\Entities;

use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

use function in_array;
use function mb_strlen;
use function sprintf;

/**
 * A backup archive row (`backups`): tracks the lifecycle of one
 * encrypted `.zip` stored in the RustFS `backups` bucket. The entity
 * does not perform I/O — use cases and the workflow services drive it.
 *
 * State transitions:
 * - `pending → processing` — the create/restore job picked up the record;
 * - `processing → completed` — the archive is uploaded (create) or the
 *   restore finished; the archive path and the content summary are
 *   stamped on the entity;
 * - `pending | processing → failed` — an error occurred;
 * - `completed → processing` — an existing backup being re-restored (the
 *   restore job mirrors the create lifecycle on the same record, which
 *   the UI polls via `GET /admin/backups/{backup}/status`).
 *
 * `completed_at` is set when the record completes **or** fails;
 * pending/processing rows keep it null.
 */
final class Backup extends BaseEntity
{
    public const MAX_FILENAME_LENGTH = 255;

    public const MAX_PATH_LENGTH = 500;

    public function __construct(
        private readonly int $id,
        private readonly string $filename,
        private ?string $path,
        private BackupContentSummary $summary,
        private BackupStatus $status,
        private readonly ?int $encryptionKeyId,
        private BackupTimings $timings,
    ) {
        $this->validateFilename($filename);
        $this->validatePath($path);
        $this->validateEncryptionKeyId($encryptionKeyId);
        $this->validateStatusState($status, $path, $summary, $timings);
    }

    /**
     * Materializes a stored `backups` row (array keys match the column
     * names: id, filename, path, size_bytes, status, encryption_key_id,
     * record_counts, assets_count, assets_size_bytes, created_at,
     * completed_at, deleted_at). The NOT NULL `path`/`size_bytes`
     * placeholders of pending rows (`''`/`0`) map back to null.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            filename: (string) $row['filename'],
            path: isset($row['path']) && $row['path'] !== '' ? (string) $row['path'] : null,
            summary: new BackupContentSummary(
                isset($row['size_bytes']) && (int) $row['size_bytes'] > 0 ? (int) $row['size_bytes'] : null,
                $row['assets_count'] !== null ? (int) $row['assets_count'] : null,
                $row['assets_size_bytes'] !== null ? (int) $row['assets_size_bytes'] : null,
                $row['record_counts'] !== null ? $row['record_counts'] : null,
            ),
            status: BackupStatus::from((string) $row['status']),
            encryptionKeyId: $row['encryption_key_id'] !== null ? (int) $row['encryption_key_id'] : null,
            timings: new BackupTimings(
                new DateTimeImmutable($row['created_at']),
                $row['completed_at'] !== null ? new DateTimeImmutable($row['completed_at']) : null,
                $row['deleted_at'] !== null ? new DateTimeImmutable($row['deleted_at']) : null,
            ),
        );
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toFilename(): string
    {
        return $this->filename;
    }

    /**
     * The RustFS path of the archive — null until the backup completed.
     */
    public function toPath(): ?string
    {
        return $this->path;
    }

    public function toSizeBytes(): ?int
    {
        return $this->summary->toSizeBytes();
    }

    /**
     * @return array<string, int>|null
     */
    public function toRecordCounts(): ?array
    {
        return $this->summary->toRecordCounts();
    }

    public function toAssetsCount(): ?int
    {
        return $this->summary->toAssetsCount();
    }

    public function toAssetsSizeBytes(): ?int
    {
        return $this->summary->toAssetsSizeBytes();
    }

    public function toStatus(): BackupStatus
    {
        return $this->status;
    }

    public function toEncryptionKeyId(): ?int
    {
        return $this->encryptionKeyId;
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->timings->toCreatedAt();
    }

    /**
     * Set when the record completed **or** failed.
     */
    public function toCompletedAt(): ?DateTimeImmutable
    {
        return $this->timings->toCompletedAt();
    }

    public function toDeletedAt(): ?DateTimeImmutable
    {
        return $this->timings->toDeletedAt();
    }

    public function canDownload(): bool
    {
        return $this->status === BackupStatus::Completed;
    }

    public function canDelete(): bool
    {
        return $this->status !== BackupStatus::Processing;
    }

    /**
     * `pending → processing` for a fresh backup, `completed → processing`
     * when an existing backup is re-restored. Clears the previous
     * completion stamp on re-processing.
     */
    public function startProcessing(): void
    {
        if ($this->status === BackupStatus::Processing) {
            throw new InvalidArgumentException('Backup is already processing.');
        }

        if (! in_array($this->status, [BackupStatus::Pending, BackupStatus::Completed], true)) {
            throw new InvalidArgumentException(
                sprintf('Backup %d cannot start processing from the %s status.', $this->id, $this->status->value),
            );
        }

        $this->status = BackupStatus::Processing;
        $this->timings = $this->timings->withoutCompletedAt();
    }

    /**
     * `processing → completed`: stamps the archive path and the
     * content summary. The summary must be fully populated — the
     * archive metadata is written atomically.
     */
    public function complete(string $rustFsPath, BackupContentSummary $summary): void
    {
        if ($this->status !== BackupStatus::Processing) {
            throw new InvalidArgumentException(
                sprintf('Backup %d cannot complete without being processed first.', $this->id),
            );
        }

        $this->validatePath($rustFsPath);

        if (! $summary->isComplete()) {
            throw new InvalidArgumentException('Backup completion requires a fully populated content summary.');
        }

        $this->path = $rustFsPath;
        $this->summary = $summary;
        $this->timings = $this->timings->withCompletedAt(new DateTimeImmutable());
        $this->status = BackupStatus::Completed;
    }

    /**
     * `pending | processing → failed`: stamps the failure moment; the
     * archive path stays untouched (a failed create never produced a
     * zip, a failed restore keeps the original archive).
     */
    public function fail(): void
    {
        if (! in_array($this->status, [BackupStatus::Pending, BackupStatus::Processing], true)) {
            throw new InvalidArgumentException(
                sprintf('Backup %d cannot fail from the %s status.', $this->id, $this->status->value),
            );
        }

        $this->timings = $this->timings->withCompletedAt(new DateTimeImmutable());
        $this->status = BackupStatus::Failed;
    }

    private function validateFilename(string $filename): void
    {
        if ($filename === '' || mb_strlen($filename) > self::MAX_FILENAME_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Backup filename must be a non-empty string of at most %d characters.', self::MAX_FILENAME_LENGTH),
            );
        }
    }

    private function validatePath(?string $path): void
    {
        if ($path !== null && ($path === '' || mb_strlen($path) > self::MAX_PATH_LENGTH)) {
            throw new InvalidArgumentException(
                sprintf('Backup path must be a non-empty string of at most %d characters.', self::MAX_PATH_LENGTH),
            );
        }
    }

    private function validateEncryptionKeyId(?int $encryptionKeyId): void
    {
        if ($encryptionKeyId !== null && $encryptionKeyId < 1) {
            throw new InvalidArgumentException('Backup encryption key id must be a positive integer.');
        }
    }

    /**
     * The per-status shape of the stored row: a completed backup
     * carries its archive path + full content summary + completion
     * time, failed rows the failure time, pending/processing rows
     * neither.
     */
    private function validateStatusState(
        BackupStatus $status,
        ?string $path,
        BackupContentSummary $summary,
        BackupTimings $timings,
    ): void {
        if ($status === BackupStatus::Completed) {
            if ($path === null || ! $summary->isComplete() || $timings->toCompletedAt() === null) {
                throw new InvalidArgumentException('A completed backup must carry its archive stamp and completion time.');
            }

            return;
        }

        if ($status === BackupStatus::Failed) {
            if ($timings->toCompletedAt() === null) {
                throw new InvalidArgumentException('A failed backup must carry its failure time.');
            }

            return;
        }

        if ($timings->toCompletedAt() !== null) {
            throw new InvalidArgumentException('A pending or processing backup must not carry a completion time.');
        }
    }
}
