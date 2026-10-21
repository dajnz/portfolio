<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\AssetPathProviderInterface;

final class FakeAssetPathProvider implements AssetPathProviderInterface
{
    /**
     * @param  string[]  $paths
     */
    public function __construct(
        public array $paths = [],
    ) {
    }

    public function paths(): array
    {
        return $this->paths;
    }
}
