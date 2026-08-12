<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Exceptions;

use RuntimeException;

/**
 * The tech tag is already attached to the project
 * (content-management.md:160) — surfaced as a 409 Conflict by the
 * controller.
 */
final class ProjectTagAlreadyAttachedException extends RuntimeException
{
    public static function forTag(int $projectId, int $skillId): self
    {
        return new self(
            "Skill [{$skillId}] is already attached to project [{$projectId}]."
        );
    }
}
