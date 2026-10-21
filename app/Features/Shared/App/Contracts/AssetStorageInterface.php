<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Reads and writes stored asset objects (the immutable RustFS assets bucket).
 * An abstraction over the framework's filesystem so the app layer never names
 * the disk; the framework-backed implementation lives on the infra layer.
 */
interface AssetStorageInterface
{
    public function exists(string $path): bool;

    public function write(string $path, string $contents): void;

    public function writeFile(string $path, string $sourcePath): void;

    public function size(string $path): int;

    /**
     * The object paths directly under the given directory.
     *
     * @return string[]
     */
    public function files(string $directory): array;

    /**
     * Every object path under the given directory, recursively.
     *
     * @return string[]
     */
    public function allFiles(string $directory): array;

    /**
     * @param  string[]  $paths
     */
    public function delete(array $paths): void;
}
