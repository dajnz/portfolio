<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Records the {@see CountCacheInvalidatorInterface} calls of the use
 * cases under test.
 */
final class FakeCountCacheInvalidator implements CountCacheInvalidatorInterface
{
    /** @var string[] */
    public array $invalidated = [];

    public function invalidate(string $entity): void
    {
        $this->invalidated[] = $entity;
    }
}
