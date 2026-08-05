<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * Domain entity representing a single tech skill.
 *
 * Every skill belongs to a group; the group's identity and localised name
 * are part of the entity so that no skill is isolated from its group context.
 *
 * @property-read TechSkillGroup|null $group — this would create a circular graph;
 *                  instead use {@see TechSkillGroup::toSkills()} to navigate the graph.
 */
final class TechSkill extends BaseEntity
{
    private int $id;
    private string $name;
    private ?string $icon;
    private int $proficiency;
    private int $sortOrder;
    private int $groupId;
    private string $groupName;

    public function __construct(
        int $id,
        string $name,
        ?string $icon,
        int $proficiency,
        int $sortOrder,
        int $groupId,
        string $groupName,
    ) {
        $this->validateName($name);
        $this->validateIcon($icon);
        $this->validateProficiency($proficiency);
        $this->validateSortOrder($sortOrder);
        $this->validateGroupName($groupName);

        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->proficiency = $proficiency;
        $this->sortOrder = $sortOrder;
        $this->groupId = $groupId;
        $this->groupName = $groupName;
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

    public function toProficiency(): int
    {
        return $this->proficiency;
    }

    public function toProficiencyLabelKey(): string
    {
        return "skills.proficiency.{$this->proficiency}";
    }

    public function toSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function toGroupId(): int
    {
        return $this->groupId;
    }

    public function toGroupName(): string
    {
        return $this->groupName;
    }

    private function validateName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Tech skill name must not be empty.');
        }
    }

    private function validateIcon(?string $icon): void
    {
        if ($icon === null) {
            return;
        }

        if (! preg_match('/^devicon:[a-zA-Z][a-zA-Z0-9\-]*$/', $icon)) {
            throw new InvalidArgumentException(
                "Tech skill icon must match the format \"devicon:*\", got \"{$icon}\"."
            );
        }
    }

    private function validateProficiency(int $proficiency): void
    {
        if ($proficiency < 1 || $proficiency > 5) {
            throw new InvalidArgumentException(
                "Tech skill proficiency must be between 1 and 5, got {$proficiency}."
            );
        }
    }

    private function validateSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new InvalidArgumentException(
                "Tech skill sort order must be a positive 0-based integer, got {$sortOrder}."
            );
        }
    }

    private function validateGroupName(string $groupName): void
    {
        if ($groupName === '') {
            throw new InvalidArgumentException('Tech skill group name must not be empty.');
        }
    }
}
