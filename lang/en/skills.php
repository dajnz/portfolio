<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Skills (shared read model, i18n-and-theming.md)
|--------------------------------------------------------------------------
|
| `proficiency` labels are referenced by the domain via
| `TechSkill::toProficiencyLabelKey()` → `skills.proficiency.{1-5}`
| (resume.md + the public skills blocks).
|
*/

return [
    'search_placeholder' => 'Search skills...',
    'start_typing' => 'Start typing to search skills',
    'no_skills_found' => 'No skills found.',
    'could_not_load_skills' => 'Could not load skills. [Retry]',
    'proficiency' => [
        1 => 'Novice',
        2 => 'Advanced Beginner',
        3 => 'Competent',
        4 => 'Proficient',
        5 => 'Expert',
    ],
];
