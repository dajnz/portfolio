<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;

/**
 * In-memory `AssetPathCollectorInterface` double for the unit suite:
 * returns the configured object paths for any bucket.
 */
final class FakeAssetPathCollector implements AssetPathCollectorInterface
{
    /**
     * @param  string[]  $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {
    }

    public function listPaths(string $bucket): array
    {
        return $this->paths;
    }
}
