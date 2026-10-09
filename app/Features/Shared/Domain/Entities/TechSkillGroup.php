<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

/**
 * Group names are translatable per-locale via {@see TechSkillGroupName}
 * value objects; the icon and sort order are not. Skills are held as a map
 * keyed by skill ID, and {@see toSkills()} returns them in insertion
 * order. Constructor arguments are value objects — repositories create
 * them from raw DB/request values.
 *
 * State changes go through the mutation methods (`rename`, `changeIcon`,
 * `rearrange`, `addSkill`/`removeSkill`); the write repository persists
 * the entity afterwards.
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
     * translation is missing.
     */
    public function nameFor(string $locale): string
    {
        $groupName = $this->groupNamesMap[$locale] ?? $this->groupNamesMap['en'] ?? null;

        return empty($groupName) ? '' : $groupName->toName();
    }

    /**
     * The group's identity + per-locale names as a value object, used when
     * a skill is moved into this group
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

    public function rearrange(SortOrder $order): void
    {
        $this->sortOrder = $order;
    }

    /**
     * Mutates the passed skill's group context and records it in the
     * internal map.
     */
    public function addSkill(TechSkill $skill, SortOrder $order): void
    {
        $skill->changeGroup($this->toGroupVo(), $order);

        $this->skills[$skill->toId()] = $skill;
    }

    /**
     * Removes the skill from the group's internal collection. The skill row
     * itself is left untouched — only the grouping changes.
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
