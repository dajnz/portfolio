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
    'proficiency' => [
        1 => 'Novice',
        2 => 'Advanced Beginner',
        3 => 'Competent',
        4 => 'Proficient',
        5 => 'Expert',
    ],
];
