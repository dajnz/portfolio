<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\ValueObjects;

/**
 * The about-info availability status (database.md:75-87): one of
 * `available`, `limited`, `busy`, stored as a varchar + CHECK constraint.
 *
 * `toLabelKey()` returns the static-translation key of the status label
 * (`landing.availability.*`) — how a status is displayed (badge colors
 * etc.) is a UI concern and stays out of the domain layer.
 */
enum AvailabilityStatus: string
{
    case Available = 'available';

    case Limited = 'limited';

    case Busy = 'busy';

    public function toLabelKey(): string
    {
        return "landing.availability.{$this->value}";
    }
}
