<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use InvalidArgumentException;

/**
 * Domain entity representing a single tech skill.
 *
 * Every skill belongs to a group; the group's identity and localised
 * names are carried as a {@see SkillGroupVo} so that no skill is
 * isolated from its group context. This is the single entity used by
 * both the Shared read model and the TechSkills write surface
 * (content-management.md:19,81). Constructor arguments are value
 * objects — repositories create them from raw DB/request values.
 *
 * State changes go through the mutation methods (`rename`, `changeIcon`,
 * `changeProficiency`, `useInResume`/`unuseInResume`, `changeGroup`);
 * the write repository persists the entity afterwards.
 */
final class TechSkill extends BaseEntity
{
    private int $id;
    private string $name;
    private ?SkillIcon $icon;
    private ProficiencyLevel $proficiency;
    private bool $includeInResume;
    private SortOrder $sortOrder;
    private SkillGroupVo $group;

    public function __construct(
        int $id,
        string $name,
        ?SkillIcon $icon,
        ProficiencyLevel $proficiency,
        bool $includeInResume,
        SortOrder $sortOrder,
        SkillGroupVo $group,
    ) {
        $this->validateSkillName($name);

        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->proficiency = $proficiency;
        $this->includeInResume = $includeInResume;
        $this->sortOrder = $sortOrder;
        $this->group = $group;
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

    public function toProficiency(): int
    {
        return $this->proficiency->toValue();
    }

    public function toProficiencyLevel(): ProficiencyLevel
    {
        return $this->proficiency;
    }

    public function toProficiencyLabelKey(): string
    {
        return "skills.proficiency.{$this->proficiency->toValue()}";
    }

    public function toIncludeInResume(): bool
    {
        return $this->includeInResume;
    }

    public function toSortOrder(): int
    {
        return $this->sortOrder->toValue();
    }

    public function toGroup(): SkillGroupVo
    {
        return $this->group;
    }

    public function toGroupId(): int
    {
        return $this->group->toId();
    }

    public function toGroupName(string $locale): string
    {
        return $this->group->groupNameFor($locale);
    }

    public function rename(string $name): void
    {
        $this->validateSkillName($name);
        $this->name = $name;
    }

    public function changeIcon(SkillIcon $icon): void
    {
        $this->icon = $icon;
    }

    public function changeProficiency(ProficiencyLevel $level): void
    {
        $this->proficiency = $level;
    }

    public function useInResume(): void
    {
        $this->includeInResume = true;
    }

    public function unuseInResume(): void
    {
        $this->includeInResume = false;
    }

    /**
     * Moves the skill into another group (drag-and-drop between groups):
     * the new group context and the new position within it are applied
     * together; the repository persists the change afterwards.
     */
    public function changeGroup(SkillGroupVo $group, SortOrder $order): void
    {
        $this->group = $group;
        $this->sortOrder = $order;
    }

    private function validateSkillName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Tech skill name must not be empty.');
        }
    }
}
