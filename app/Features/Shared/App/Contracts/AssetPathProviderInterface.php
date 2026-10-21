<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Supplies every stored asset path one domain area considers referenced.
 * The orphan cleanup unions all registered providers and deletes the
 * objects no provider claims.
 */
interface AssetPathProviderInterface
{
    /**
     * @return string[]
     */
    public function paths(): array;
}
