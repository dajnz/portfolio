<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use DateTimeImmutable;

use function sprintf;

/**
 * The B18 archive filename conventions: `backup-{Y-m-d-His}-k{id}.zip`
 * for created backups (the `-k{id}` suffix identifies the encryption
 * key for offline decryption, backup.md:82) and
 * `backup-import-{Y-m-d-His}-k{id}.zip` for DR-imported archives
 * (backup.md:418). The pending `backups` row carries the final name —
 * the create workflow produces the archive with exactly this filename,
 * so the record, the RustFS object key and the offline artifact always
 * agree.
 */
final class BackupFilename
{
    public static function for(DateTimeImmutable $createdAt, ?int $encryptionKeyId = null): string
    {
        $suffix = $encryptionKeyId !== null ? sprintf('-k%d', $encryptionKeyId) : '';

        return sprintf('backup-%s%s.zip', $createdAt->format('Y-m-d-His'), $suffix);
    }

    public static function forImport(DateTimeImmutable $createdAt, int $encryptionKeyId): string
    {
        return sprintf('backup-import-%s-k%d.zip', $createdAt->format('Y-m-d-His'), $encryptionKeyId);
    }
}
