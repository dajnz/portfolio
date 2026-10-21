<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\AssetStorageInterface;

final class FakeAssetStorage implements AssetStorageInterface
{
    /**
     * @param  string[]  $allFiles
     */
    public function __construct(
        public array $allFiles = [],
    ) {
    }

    /** @var list<string[]> */
    public array $deletedBatches = [];

    public function exists(string $path): bool
    {
        return in_array($path, $this->allFiles, true);
    }

    public function write(string $path, string $contents): void
    {
    }

    public function writeFile(string $path, string $sourcePath): void
    {
    }

    public function size(string $path): int
    {
        return 0;
    }

    public function files(string $directory): array
    {
        return $this->allFiles;
    }

    public function allFiles(string $directory): array
    {
        return $this->allFiles;
    }

    public function delete(array $paths): void
    {
        $this->deletedBatches[] = $paths;
    }
}
