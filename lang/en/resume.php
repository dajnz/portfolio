<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Resume PDF (content-management.md — resume generation)
|--------------------------------------------------------------------------
|
| Section headers rendered in the generated resume PDF. The proficiency
| labels live in `skills.proficiency` (used by both the public skills
| blocks and the resume via `TechSkill::toProficiencyLabelKey()`).
|
*/

return [
    'section_header' => 'Header',
    'section_about_me' => 'About Me',
    'section_skills' => 'Skills',
    'section_experience' => 'Work Experience',
    'section_contacts' => 'Contacts',
    'present' => 'Present',
];
