<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

use App\Features\Backup\Domain\ValueObjects\Manifest;

/**
 * The encrypted `.zip` archive surface: create/validate/extract/
 * readManifest around PHP's `ZipArchive` with per-entry AES-256
 * (`setEncryptionName`). The encryption key is the raw 44-character
 * base64 value of the active (or imported) `backup_encryption_keys`
 * row; when `config('backup.encryption_enabled')` is false, create skips
 * encryption and validate/readManifest accept any key. Implemented by
 * `ZipBackupArchive` in the Infra layer.
 */
interface BackupArchiveInterface
{
    /**
     * Archives every file under `$sourceDir` into a new `.zip`,
     * encrypting each entry with the given key, and returns the
     * created archive's path.
     */
    public function create(string $sourceDir, string $encryptionKey): string;

    /**
     * Opens the archive with the key and verifies it is a valid
     * backup: `manifest.json` must exist and parse — the DR import's
     * "invalid key or corrupted archive" check. Throws when the
     * archive is corrupt or the key is wrong.
     */
    public function validate(string $path, string $encryptionKey): Manifest;

    /**
     * Opens the archive with the key and extracts every entry into
     * `$destDir`.
     */
    public function extract(string $path, string $encryptionKey, string $destDir): void;

    /**
     * Reads and parses the archive's `manifest.json` entry — used by
     * the DR import before the archive is uploaded.
     */
    public function readManifest(string $path, string $encryptionKey): Manifest;
}
