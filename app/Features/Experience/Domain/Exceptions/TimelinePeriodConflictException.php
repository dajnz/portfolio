<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\Exceptions;

use DomainException;

/**
 * TimelinePeriodConflictException: the submitted period breaks the
 * timeline-continuity rules (ADR-0024) — it overlaps its predecessor or
 * successor, or only-the-last-item-can-be-ongoing is violated. Carries
 * the form field the error should surface on; the controller maps it to
 * a `ValidationException` (the DuplicateSkillNameException pattern).
 */
final class TimelinePeriodConflictException extends DomainException
{
    public function __construct(
        private readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function toField(): string
    {
        return $this->field;
    }
}
