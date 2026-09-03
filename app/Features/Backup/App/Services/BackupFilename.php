<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use DateTimeImmutable;

use function preg_replace;
use function sprintf;

/**
 * The archive filename conventions: `backup-{Y-m-d-His}-k{id}--{name}.zip`
 * for created backups and `backup-import-{Y-m-d-His}-k{id}--{name}.zip`
 * for DR-imported archives. The `-k{id}` suffix identifies the
 * encryption key for offline decryption (backup.md:82); the `--{name}`
 * part appends the key's display name (the same name the admin sees in
 * the keys list / at generation time) so the offline-saved key entry —
 * name + raw value — maps back to the archive without consulting the
 * database. The name is sanitized to `[A-Za-z0-9._-]` runs (dashes for
 * anything else); it is for human readability only — key resolution is
 * always done from the record's `encryption_key_id`. The pending
 * `backups` row carries the final name — the create workflow produces
 * the archive with exactly this filename, so the record, the RustFS
 * object key and the offline artifact always agree.
 */
final class BackupFilename
{
    public static function for(
        DateTimeImmutable $createdAt,
        ?int $encryptionKeyId = null,
        ?string $encryptionKeyName = null,
    ): string {
        $suffix = $encryptionKeyId !== null ? sprintf('-k%d', $encryptionKeyId) : '';
        $nameSuffix = self::nameSuffix($encryptionKeyName);

        return sprintf('backup-%s%s%s.zip', $createdAt->format('Y-m-d-His'), $suffix, $nameSuffix);
    }

    public static function forImport(
        DateTimeImmutable $createdAt,
        int $encryptionKeyId,
        string $encryptionKeyName,
    ): string {
        return sprintf(
            'backup-import-%s-k%d%s.zip',
            $createdAt->format('Y-m-d-His'),
            $encryptionKeyId,
            self::nameSuffix($encryptionKeyName),
        );
    }

    private static function nameSuffix(?string $encryptionKeyName): string
    {
        if ($encryptionKeyName === null || $encryptionKeyName === '') {
            return '';
        }

        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', $encryptionKeyName);

        return $sanitized === null ? '' : '--'.$sanitized;
    }
}
