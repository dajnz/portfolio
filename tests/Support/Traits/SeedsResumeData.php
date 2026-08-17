<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Shared fixtures for the Resume test suites (step 10.1): the single-row
 * resume_config (default layout normalized to the validated order range
 * 0–4 — B177) and the resume PDF source data (about info, skills,
 * contacts, a published experience item with a tech tag).
 */
trait SeedsResumeData
{
    protected const DEFAULT_LAYOUT = [
        'template' => 'modern',
        'sections' => [
            'header' => ['visible' => true, 'order' => 0],
            'about_me' => ['visible' => true, 'order' => 1],
            'skills' => ['visible' => true, 'order' => 2],
            'experience' => ['visible' => true, 'order' => 3],
            'contacts' => ['visible' => true, 'order' => 4],
        ],
    ];

    protected function seedResumeConfig(array $overrides = []): void
    {
        DB::table('resume_config')->insert(array_merge([
            'id' => 1,
            'mode' => 'auto',
            'layout' => json_encode(self::DEFAULT_LAYOUT),
            'custom_resume_file' => null,
        ], $overrides));
    }

    /**
     * Seeds the full resume source dataset: about info (en + ru), a skill
     * group with one included skill, an included contact and a published,
     * included experience item tagged with the skill.
     */
    protected function seedResumeSourceData(): void
    {
        // Raw inserts: the models' fillable sets deliberately exclude `id`.
        DB::table('about_info')->insert([
            'id' => 1,
            'show_location' => true,
            'relocation_readiness' => false,
            'work_type' => 'remote',
        ]);
        DB::table('about_info_translations')->insert([
            ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex Kovalev', 'title' => 'Full-Stack Developer', 'short_pitch' => 'I build fast web apps.', 'location' => null],
            ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс Ковалёв', 'title' => 'Фулстек-разработчик', 'short_pitch' => 'Я создаю быстрые веб-приложения.', 'location' => null],
        ]);

        $groupId = DB::table('skill_groups')->insertGetId([
            'sort_order' => 0,
            'icon' => 'heroicons:server',
        ]);
        DB::table('skill_group_translations')->insert([
            ['skill_group_id' => $groupId, 'locale' => 'en', 'name' => 'Backend'],
            ['skill_group_id' => $groupId, 'locale' => 'ru', 'name' => 'Бэкенд'],
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'skill_group_id' => $groupId,
            'name' => 'Laravel',
            'icon' => 'devicon:laravel',
            'proficiency' => 5,
            'include_in_resume' => true,
            'sort_order' => 0,
        ]);

        $contactId = DB::table('contacts')->insertGetId([
            'value' => 'octocat',
            'icon' => 'simple-icons:github',
            'url' => 'https://github.com/alex',
            'include_in_resume' => true,
            'sort_order' => 0,
        ]);
        DB::table('contact_translations')->insert([
            'contact_id' => $contactId,
            'locale' => 'en',
            'name' => 'GitHub',
        ]);

        $itemId = DB::table('experience_timeline_items')->insertGetId([
            'started_at' => '2023-01-15',
            'ended_at' => null,
            'is_published' => true,
            'sort_order' => 0,
            'include_in_resume' => true,
        ]);
        DB::table('experience_timeline_item_translations')->insert([
            'item_id' => $itemId,
            'locale' => 'en',
            'position' => 'Senior Engineer',
            'company_name' => 'Acme Inc',
            'company_country' => 'Germany',
            'short_description' => 'Led the payments team.',
            'achievements' => json_encode([
                ['description' => 'Cut checkout latency by 40%.', 'project_link' => null],
            ]),
        ]);
        DB::table('experience_item_tech_tags')->insert([
            'experience_item_id' => $itemId,
            'skill_id' => $skillId,
        ]);
    }
}
