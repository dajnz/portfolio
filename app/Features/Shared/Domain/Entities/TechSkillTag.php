<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use InvalidArgumentException;

/**
 * Light read-model representation of a tech skill (the `skills` row) as
 * attached to an aggregate via a pivot table — `experience_item_tech_tags`
 * (experience timeline cards, content-management.md:174) and
 * `project_tech_tags` (project cards, the landing's featured projects).
 * Carries only the identity, the display name and the icon
 * (`devicon:*` — the same invariant as {@see TechSkill}, enforced by
 * the shared {@see SkillIcon} value object). The caller builds the icon
 * VO — the entity never constructs value objects itself.
 */
final class TechSkillTag extends BaseEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $skillName,
        private readonly ?SkillIcon $icon,
    ) {
        if ($skillName === '') {
            throw new InvalidArgumentException('Tech skill tag name must not be empty.');
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toSkillName(): string
    {
        return $this->skillName;
    }

    public function toIcon(): ?string
    {
        return $this->icon?->toValue();
    }
}
