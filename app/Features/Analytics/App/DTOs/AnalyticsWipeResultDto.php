<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\DTOs;

/**
 * The `analytics:wipe` result (analytics.md "Data deletion"): the
 * per-table deleted row counts, surfaced by the command's output.
 */
final class AnalyticsWipeResultDto
{
    public function __construct(
        public readonly int $events,
        public readonly int $metrics,
        public readonly int $reports,
    ) {
    }
}
