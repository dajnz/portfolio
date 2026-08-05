<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * Domain entity representing a tech skill group.
 *
 * A group may contain zero or more {@see TechSkill} entities, navigable via
 * {@see toSkills()}.
 */
final class TechSkillGroup extends BaseEntity
{
    private int $id;
    private string $name;
    private ?string $icon;
    private int $sortOrder;

    /** @var TechSkill[] */
    private array $skills;

    /**
     * @param  TechSkill[]  $skills
     */
    public function __construct(
        int $id,
        string $name,
        ?string $icon,
        int $sortOrder,
        array $skills,
    ) {
        $this->validateName($name);
        $this->validateIcon($icon);
        $this->validateSortOrder($sortOrder);

        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->sortOrder = $sortOrder;
        $this->skills = $skills;
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

    public function toSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @return TechSkill[]
     */
    public function toSkills(): array
    {
        return $this->skills;
    }

    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Tech skill group name must not be empty.');
        }
    }

    private function validateIcon(?string $icon): void
    {
        if ($icon === null) {
            return;
        }

        if (! preg_match('/^heroicons:[a-zA-Z][a-zA-Z0-9\-]*$/', $icon)) {
            throw new InvalidArgumentException(
                "Tech skill group icon must match the format \"heroicons:*\", got \"{$icon}\"."
            );
        }
    }

    private function validateSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new InvalidArgumentException(
                "Tech skill group sort order must be a positive 0-based integer, got {$sortOrder}."
            );
        }
    }
}
