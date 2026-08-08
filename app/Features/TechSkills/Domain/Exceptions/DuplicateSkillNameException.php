<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Domain\Exceptions;

use DomainException;

/** DuplicateSkillNameException: a skill name collides with another skill; surfaced as a validation error by the controller. */
final class DuplicateSkillNameException extends DomainException
{
}
