<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * A tech tag of a featured project (read-model of the
 * `project_tech_tags` ↔ `skills` join — the landing page contract
 * `FeaturedProjectData.tech_tags`: `{id, name, icon}`).
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
}
