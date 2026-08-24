<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

use function is_dir;
use function mkdir;
use function rmdir;
use function unlink;

/**
 * The workflow staging area (backup.md:391): a fresh subdirectory per
 * run under `config('backup.staging_path')` (`storage/app/backups/tmp/`),
 * recursively removed on completion or failure. Local-only — never
 * scanned by `app:cleanup-orphaned-files` and never included in
 * backup contents.
 */
final class StagingDirectory
{
    public static function create(string $basePath): string
    {
        $dir = $basePath.'/'.bin2hex(random_bytes(8));

        if (! mkdir($dir, 0775, recursive: true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create the backup staging directory '{$dir}'.");
        }

        return $dir;
    }

    public static function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
