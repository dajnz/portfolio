<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Domain\Exceptions;

use DomainException;

/** Thrown when a skill name collides with another skill; surfaced as a validation error. */
final class DuplicateSkillNameException extends DomainException
{
}
