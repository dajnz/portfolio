<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * A tech tag of a featured project (read-model of the
 * `project_tech_tags` ↔ `skills` join — the landing page contract
 * `FeaturedProjectData.tech_tags`: `{id, name, icon}`). Icons come from
 * the `skills.icon` column, so the non-empty icon must match the
 * `devicon:*` format (same invariant as `TechSkill`).
 */
final class FeaturedProjectTechTag extends BaseEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly ?string $icon,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Project tech tag name must not be empty.');
        }

        $this->assertValidIcon($icon);
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
        return $this->icon;
    }

    private function assertValidIcon(?string $icon): void
    {
        if ($icon === null) {
            return;
        }

        if (preg_match('/^devicon:[a-zA-Z][a-zA-Z0-9\-]*$/', $icon) !== 1) {
            throw new InvalidArgumentException(
                "Project tech tag icon must match the format \"devicon:*\", got \"{$icon}\".",
            );
        }
    }
}
