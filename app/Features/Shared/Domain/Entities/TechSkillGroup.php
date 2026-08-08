<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

/**
 * Domain entity representing a tech skill group.
 *
 * Group names are translatable via per-locale
 * {@see TechSkillGroupName} value objects; icon and sort order are not.
 * The group carries its skills as a map keyed by skill ID
 * ({@see toSkills()} returns them in insertion order). This is the
 * single entity used by both the Shared read model and the TechSkills
 * write surface. Constructor arguments are value objects — repositories
 * create them from raw DB/request values.
 *
 * State changes go through the mutation methods (`rename`,
 * `changeIcon`, `rearrange`, `addSkill`/`removeSkill`); the write
 * repository persists the entity afterwards.
 */
final class TechSkillGroup extends BaseEntity
{
    private int $id;
    private ?SkillGroupIcon $icon;
    private SortOrder $sortOrder;
    /** @var array<string, TechSkillGroupName> Keyed by locale*/
    private array $groupNamesMap;
    /** @var array<int, TechSkill>  Keyed by skill ID. */
    private array $skills;

    /**
     * @param  TechSkillGroupName[]  $groupNames
     * @param  TechSkill[]  $skills  Indexed by skill ID on construction.
     */
    public function __construct(
        int $id,
        ?SkillGroupIcon $icon,
        SortOrder $sortOrder,
        array $groupNames,
        array $skills,
    ) {
        $this->id = $id;
        $this->icon = $icon;
        $this->sortOrder = $sortOrder;
        $this->groupNamesMap = $this->namesArrayToMap($groupNames);
        $this->skills = $this->indexById($skills);
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toIcon(): ?string
    {
        return $this->icon?->toValue();
    }

    public function toSortOrder(): int
    {
        return $this->sortOrder->toValue();
    }

    /**
     * @return TechSkillGroupName[]
     */
    public function toGroupNames(): array
    {
        return array_values($this->groupNamesMap);
    }

    /**
     * @return TechSkill[]
     */
    public function toSkills(): array
    {
        return array_values($this->skills);
    }

    /**
     * Group names keyed by locale (`locale => name`).
     *
     * @return array<string, string>
     */
    public function groupNamesByLocale(): array
    {
        $map = [];

        foreach ($this->groupNamesMap as $locale => $groupName) {
            $map[$locale] = $groupName->toName();
        }

        return $map;
    }

    /**
     * Localized name for the given locale, falling back to `en` when the
     * requested translation is missing (content-management.md:415).
     */
    public function nameFor(string $locale): string
    {
        $groupName = $this->groupNamesMap[$locale] ?? $this->groupNamesMap['en'] ?? null;

        return empty($groupName) ? '' : $groupName->toName();
    }

    /**
     * The group's identity + per-locale names as a value object — used
     * when a skill is moved into this group
     * ({@see TechSkill::changeGroup()}).
     */
    public function toGroupVo(): SkillGroupVo
    {
        return new SkillGroupVo(id: $this->id, groupNames: $this->toGroupNames());
    }

    public function rename(string $name, string $locale): void
    {
        $this->groupNamesMap[$locale] = new TechSkillGroupName(locale: $locale, name: $name);
    }

    public function changeIcon(SkillGroupIcon $icon): void
    {
        $this->icon = $icon;
    }

    /**
     * Reorders the group itself (drag-and-drop of groups); the
     * repository persists the change afterwards.
     */
    public function rearrange(SortOrder $order): void
    {
        $this->sortOrder = $order;
    }

    /**
     * Adds a skill to the group (drag-and-drop into a group): the skill
     * is moved into this group at the given position and stored in the
     * internal map.
     */
    public function addSkill(TechSkill $skill, SortOrder $order): void
    {
        $skill->changeGroup($this->toGroupVo(), $order);

        $this->skills[$skill->toId()] = $skill;
    }

    /**
     * Removes the skill from the group's internal collection
     * (drag-and-drop out of a group). The skill row itself is left
     * untouched — only the grouping changes.
     */
    public function removeSkill(TechSkill $skill): void
    {
        unset($this->skills[$skill->toId()]);
    }

    /**
     * @param  TechSkill[]  $skills
     * @return array<int, TechSkill>
     */
    private function indexById(array $skills): array
    {
        $map = [];
        foreach ($skills as $skill) {
            $map[$skill->toId()] = $skill;
        }

        return $map;
    }

    /**
     * @param TechSkillGroupName[] $names
     * @return array<string, TechSkillGroupName>
     */
    private function namesArrayToMap(array $names): array
    {
        $map = [];

        foreach ($names as $groupName) {
            $map[$groupName->toLocale()] = $groupName;
        }

        return $map;
    }
}
