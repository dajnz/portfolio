<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\ValueObjects;

/**
 * The about-info availability status (database.md:75-87): one of
 * `available`, `limited`, `busy`, stored as a varchar + CHECK constraint.
 *
 * `toVariant()` maps the status to the public AboutBlock badge variant
 * (`success`/`warning`/`danger` per public-section-components.md:929-942);
 * the frontend receives the raw enum value and derives the label
 * client-side from the translation catalog.
 */
enum AvailabilityStatus: string
{
    case Available = 'available';

    case Limited = 'limited';

    case Busy = 'busy';

    public function toVariant(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Limited => 'warning',
            self::Busy => 'danger',
        };
    }
}
