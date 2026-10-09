<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Mappers;

use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Infra\Models\BackupModel;

/**
 * Converts between `backups` rows and the `Backup` entity: `toRow`
 * serializes the entity into the stored row (the NOT NULL `path`/
 * `size_bytes` placeholders of pending rows, ADR-0018), `toEntity`
 * materializes a model row through the entity's `fromRow()` — the
 * single row-to-entity path (the placeholders map back to null).
 */
final class BackupMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toRow(Backup $backup): array
    {
        return [
            'filename' => $backup->toFilename(),
            'size_bytes' => $backup->toSizeBytes() ?? 0,
            'path' => $backup->toPath() ?? '',
            'record_counts' => $backup->toRecordCounts(),
            'assets_count' => $backup->toAssetsCount(),
            'assets_size_bytes' => $backup->toAssetsSizeBytes(),
            'status' => $backup->toStatus()->value,
            'encryption_key_id' => $backup->toEncryptionKeyId(),
            'created_at' => $backup->toCreatedAt()->format('Y-m-d H:i:s'),
            'completed_at' => $backup->toCompletedAt()?->format('Y-m-d H:i:s'),
            'deleted_at' => $backup->toDeletedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function toEntity(BackupModel $model): Backup
    {
        // `toArray()` applies the model's casts — `record_counts` comes
        // back as an array and the dates as ISO strings the entity's
        // `fromRow()` parses. `deleted_at` is only present once set
        // (SoftDeletes) — default it to null for the row shape.
        $row = $model->toArray();
        $row['deleted_at'] ??= null;

        return Backup::fromRow($row);
    }
}
