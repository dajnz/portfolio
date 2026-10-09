<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\ValueObjects;

/**
 * The about-info work type (CHECK `work_type IN ('remote', 'hybrid',
 * 'office')`).
 */
enum WorkType: string
{
    case Remote = 'remote';

    case Hybrid = 'hybrid';

    case Office = 'office';
}
