<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use InvalidArgumentException;

/**
 * A tech tag of a featured project (read-model of the
 * `project_tech_tags` ↔ `skills` join — the landing page contract
 * `FeaturedProjectData.tech_tags`: `{id, name, icon}`). Icons come from
 * the `skills.icon` column, so the icon must match the `devicon:*`
 * format (same invariant as `TechSkill`, enforced by the shared
 * {@see SkillIcon} value object).
 */
final class FeaturedProjectTechTag extends BaseEntity
{
    private readonly ?SkillIcon $icon;

    public function __construct(
        private readonly int $id,
        private readonly string $name,
        ?string $icon,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Project tech tag name must not be empty.');
        }

        $this->icon = $icon === null ? null : new SkillIcon($icon);
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toName(): string
    {
        return $this->name;
    }

    public function toIcon(): ?string
    {
        return $this->icon?->toValue();
    }
}
