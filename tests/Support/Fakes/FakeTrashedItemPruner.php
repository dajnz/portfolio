<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\TrashedItemPrunerInterface;
use DateTimeImmutable;

final class FakeTrashedItemPruner implements TrashedItemPrunerInterface
{
    /** @var list<DateTimeImmutable> */
    public array $cutoffs = [];

    public function __construct(
        public int $deleted = 0,
    ) {
    }

    public function prune(DateTimeImmutable $cutoff): int
    {
        $this->cutoffs[] = $cutoff;

        return $this->deleted;
    }
}
