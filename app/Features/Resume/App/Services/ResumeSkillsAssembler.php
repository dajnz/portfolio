<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Services;

use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * Assembles the resume's skills section: the `include_in_resume = true` skills
 * grouped by `skill_groups.sort_order` and ordered by `skills.sort_order` (the
 * Shared read repository's native shape), with the localized proficiency label.
 */
final class ResumeSkillsAssembler
{
    public function __construct(
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<int, array{group: string, skills: array<int, array{name: string, proficiency: int, proficiency_label: string}>}>
     */
    public function forLocale(string $locale): array
    {
        $groups = [];

        foreach ($this->skills->findAllGrouped($locale) as $group) {
            $skills = [];

            foreach ($group->toSkills() as $skill) {
                if (! $skill->toIncludeInResume()) {
                    continue;
                }

                $skills[] = [
                    'name' => $skill->toName(),
                    'proficiency' => $skill->toProficiency(),
                    'proficiency_label' => $this->translator->translate($skill->toProficiencyLabelKey()),
                ];
            }

            if ($skills !== []) {
                $groups[] = [
                    'group' => $group->nameFor($locale),
                    'skills' => $skills,
                ];
            }
        }

        return $groups;
    }
}
