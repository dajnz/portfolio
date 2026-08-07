<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\ValueObjects;

/**
 * The about-info work type (database.md:75-87, CHECK
 * `work_type IN ('remote', 'hybrid', 'office')`).
 */
enum WorkType: string
{
    case Remote = 'remote';

    case Hybrid = 'hybrid';

    case Office = 'office';
}
