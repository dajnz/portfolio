<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: the project reference of a timeline item — either a
 * portfolio project (`experience_timeline_items.project_id`) or an
 * external, non-portfolio project name (`external_project_name`) with an
 * optional URL (`external_project_url`). Exactly ONE of the two project
 * kinds is set; an item without a project reference is represented by a
 * null `RelatedProject` on the
 * {@see \App\Features\Experience\Domain\Entities\TimelineItem} entity.
 * Immutable; the exclusivity invariants live here, not in the entity.
 */
final readonly class RelatedProject
{
    public function __construct(
        private ?int $ownProjectId,
        private ?string $externalProjectName,
        private ?string $externalProjectUrl,
    ) {
        if (($ownProjectId === null) === ($externalProjectName === null)) {
            throw new InvalidArgumentException(
                'A related project must reference either a portfolio project or an external project name, not both or neither.'
            );
        }

        if ($externalProjectUrl !== null && $externalProjectName === null) {
            throw new InvalidArgumentException(
                'An external project URL requires an external project name.'
            );
        }

        if ($externalProjectUrl !== null && filter_var($externalProjectUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException(
                "External project URL must be a valid URL, got \"{$externalProjectUrl}\"."
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

    public function toExternalProjectUrl(): ?string
    {
        return $this->externalProjectUrl;
    }
}
