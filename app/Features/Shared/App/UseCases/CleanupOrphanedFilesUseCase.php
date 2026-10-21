<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\Contracts\AssetPathProviderInterface;
use App\Features\Shared\App\Contracts\AssetStorageInterface;
use App\Features\Shared\App\DTOs\CleanupOrphanedFilesResultDto;

/**
 * Diffs the assets bucket against every registered reference provider and
 * removes the unreferenced objects. A dry run reports the same diff
 * without deleting.
 */
final class CleanupOrphanedFilesUseCase
{
    /**
     * @param  AssetPathProviderInterface[]  $providers
     */
    public function __construct(
        private readonly AssetStorageInterface $storage,
        private readonly array $providers,
    ) {
    }

    public function cleanup(bool $dryRun): CleanupOrphanedFilesResultDto
    {
        $referenced = $this->referencedPaths();
        $orphans = array_values(array_diff($this->storage->allFiles('/'), $referenced));

        if (! $dryRun) {
            $this->storage->delete($orphans);
        }

        return new CleanupOrphanedFilesResultDto($dryRun, $orphans);
    }

    /**
     * @return string[]
     */
    private function referencedPaths(): array
    {
        $paths = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->paths() as $path) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }
}
