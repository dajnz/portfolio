<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Invalidates the assembled props of the anonymous public pages. The
 * thumbnail jobs run after the admin mutation that already flushed the
 * cache, so they flush it again once the generated pair must appear in
 * the cards.
 */
interface PublicCacheInvalidatorInterface
{
    public function invalidate(): void;
}
