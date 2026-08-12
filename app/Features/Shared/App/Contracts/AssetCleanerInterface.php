<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Deletes stored asset files (RustFS paths — images uploaded by the
 * admin surfaces). The orchestration (WHICH paths are obsolete) lives
 * in the app layer — use cases compute the paths from the domain
 * state and delegate the actual deletion here; the framework-backed
 * implementation lives on the infra layer.
 */
interface AssetCleanerInterface
{
    /**
     * Deletes the given stored paths (missing paths are ignored).
     *
     * @param  string[]  $paths
     */
    public function delete(array $paths): void;
}
