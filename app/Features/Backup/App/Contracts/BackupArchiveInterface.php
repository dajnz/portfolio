<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

use App\Features\Backup\Domain\ValueObjects\Manifest;

/**
 * The encrypted `.zip` archive surface (backup.md:187-193, 306-335):
 * create/validate/extract/readManifest around PHP's `ZipArchive` with
 * per-entry AES-256 (`setEncryptionName`). The encryption key is the
 * raw 44-character base64 value of the active (or imported)
 * `backup_encryption_keys` row; when `config('backup.encryption_enabled')`
 * is false, create skips encryption and validate/readManifest accept
 * any key (backup.md:503-504). Implemented by `ZipBackupArchive` in
 * the Infra layer.
 */
interface BackupArchiveInterface
{
    /**
     * Archives every file under `$sourceDir` into a new `.zip`,
     * encrypting each entry with the given key, and returns the
     * created archive's path (backup.md:188-191).
     */
    public function create(string $sourceDir, string $encryptionKey): string;

    /**
     * Opens the archive with the key and verifies it is a valid
     * backup: `manifest.json` must exist and parse — the DR import's
     * "invalid key or corrupted archive" check (backup.md:192-195,413).
     * Throws when the archive is corrupt or the key is wrong.
     */
    public function validate(string $path, string $encryptionKey): Manifest;

    /**
     * Opens the archive with the key and extracts every entry into
     * `$destDir` (backup.md:196-198).
     */
    public function extract(string $path, string $encryptionKey, string $destDir): void;

    /**
     * Reads and parses the archive's `manifest.json` entry
     * (backup.md:199-201) — used by the DR import before the archive
     * is uploaded.
     */
    public function readManifest(string $path, string $encryptionKey): Manifest;
}
