<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: the project reference of a timeline item
 * (database.md:296,298,305) — either a portfolio project
 * (`experience_timeline_items.project_id`) or an external, non-portfolio
 * project name (`external_project_name`). Exactly ONE of the two is set;
 * an item without a project reference is represented by a null
 * `RelatedProject` on the {@see \App\Features\Experience\Domain\Entities\TimelineItem}
 * entity. Immutable; the exclusivity invariant lives here, not in the
 * entity.
 */
final readonly class RelatedProject
{
    public function __construct(
        private ?int $ownProjectId,
        private ?string $externalProjectName,
    ) {
        if (($ownProjectId === null) === ($externalProjectName === null)) {
            throw new InvalidArgumentException(
                'A related project must reference either a portfolio project or an external project name, not both or neither.'
            );
        }
    }

    public function toOwnProjectId(): ?int
    {
        return $this->ownProjectId;
    }

    public function toExternalProjectName(): ?string
    {
        return $this->externalProjectName;
    }
}
