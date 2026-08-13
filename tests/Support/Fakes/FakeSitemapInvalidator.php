<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;

/**
 * Records the {@see SitemapInvalidatorInterface} calls of the use
 * cases under test.
 */
final class FakeSitemapInvalidator implements SitemapInvalidatorInterface
{
    public int $invalidations = 0;

    public function invalidate(): void
    {
        $this->invalidations++;
    }
}
