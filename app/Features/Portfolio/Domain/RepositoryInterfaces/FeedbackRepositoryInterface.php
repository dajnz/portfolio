<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\RepositoryInterfaces;

use App\Features\Portfolio\Domain\Entities\Feedback;

/**
 * Persistence of `feedback` submissions. Rows are immutable apart from
 * the read/forwarded flags.
 */
interface FeedbackRepositoryInterface
{
    public function findById(int $id): ?Feedback;

    public function store(Feedback $feedback): void;

    public function markRead(int $id): void;

    public function markForwarded(int $id): void;

    public function delete(int $id): void;
}
