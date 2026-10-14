<?php

declare(strict_types=1);

namespace Tests\Support\Backend;

use Closure;

/**
 * Materializes a throwaway `.git` directory tree for version-reader tests:
 * the caller supplies a map of paths (relative to the repository root) to
 * file contents, and the tree is removed once the callback returns.
 */
final class GitFixture
{
    /**
     * @param  array<string, string>  $files  repository-root-relative path => contents
     */
    public static function with(array $files, Closure $test): mixed
    {
        $root = sys_get_temp_dir() . '/git-fixture-' . bin2hex(random_bytes(6));

        try {
            self::write($root, $files);

            return $test($root);
        } finally {
            self::remove($root);
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    public static function write(string $root, array $files): void
    {
        foreach ($files as $relative => $contents) {
            $path = $root . '/' . $relative;
            $directory = dirname($path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($path, $contents);
        }
    }

    private static function remove(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        $entries = scandir($root) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $root . '/' . $entry;
            is_dir($path) ? self::remove($path) : unlink($path);
        }

        rmdir($root);
    }
}
