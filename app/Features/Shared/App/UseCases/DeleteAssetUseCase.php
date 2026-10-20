<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\Contracts\AssetStorageInterface;

final class DeleteAssetUseCase
{
    public function __construct(
        private readonly AssetStorageInterface $storage,
    ) {
    }

    public function delete(string $path): bool
    {
        if (! $this->storage->exists($path)) {
            return false;
        }

        $this->storage->delete([$path]);

        return true;
    }
}
