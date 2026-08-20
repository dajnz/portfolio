<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use App\Features\Resume\Infra\Services\ResumePdfRenderer;

/**
 * The Blade + dompdf renderer: every committed template produces a real
 * PDF for every locale. The per-locale content (headings, texts) is
 * asserted at the assembly level (see PublicResumeControllerTest's
 * renderer spy) — dompdf compresses the output, so the PDF text layer is
 * not directly searchable here.
 */
describe('ResumePdfRenderer', function (): void {
    $data = [
        'locale' => 'en',
        'sections' => [
            'header' => ['visible' => true, 'order' => 0],
            'about_me' => ['visible' => true, 'order' => 1],
            'skills' => ['visible' => true, 'order' => 2],
            'experience' => ['visible' => true, 'order' => 3],
            'contacts' => ['visible' => true, 'order' => 4],
        ],
        'header' => ['name' => 'Alex Kovalev', 'title' => 'Full-Stack Developer'],
        'about' => 'I build fast web apps.',
        'skills' => [
            ['group' => 'Backend', 'skills' => [['name' => 'Laravel', 'proficiency' => 5, 'proficiency_label' => 'Expert']]],
        ],
        'experience' => [[
            'position' => 'Senior Engineer',
            'company_name' => 'Acme Inc',
            'company_country' => 'Germany',
            'started_at' => '2023-01-15',
            'ended_at' => null,
            'achievements' => [['description' => 'Cut latency.', 'project_link' => null]],
            'tech_tags' => ['Laravel'],
        ]],
        'contacts' => [['name' => 'GitHub', 'value' => 'octocat', 'icon' => 'simple-icons:github', 'url' => 'https://github.com/alex']],
    ];

    it('produces a PDF for every template', function (string $template) use ($data): void {
        $pdf = app(ResumePdfRenderer::class)->render($template, 'en', $data);

        expect($pdf)->toStartWith('%PDF')
            ->and(strlen($pdf))->toBeGreaterThan(500);
    })->with(['modern', 'classic', 'compact']);

    it('renders the Russian locale without errors (Cyrillic, DejaVu)', function () use ($data): void {
        app()->setLocale('ru');

        $ruData = array_merge($data, [
            'locale' => 'ru',
            'header' => ['name' => 'Алекс Ковалёв', 'title' => 'Фулстек-разработчик'],
            'about' => 'Я создаю быстрые веб-приложения.',
        ]);

        $pdf = app(ResumePdfRenderer::class)->render('modern', 'ru', $ruData);

        expect($pdf)->toStartWith('%PDF')
            ->and(strlen($pdf))->toBeGreaterThan(500);
    });

    it('renders with hidden sections and empty lists', function () use ($data): void {
        $sparse = array_merge($data, [
            'sections' => [
                'header' => ['visible' => true, 'order' => 0],
                'about_me' => ['visible' => false, 'order' => 1],
                'skills' => ['visible' => true, 'order' => 2],
                'experience' => ['visible' => true, 'order' => 3],
                'contacts' => ['visible' => false, 'order' => 4],
            ],
            'about' => '',
            'skills' => [],
            'contacts' => [],
        ]);

        $pdf = app(ResumePdfRenderer::class)->render('classic', 'en', $sparse);

        expect($pdf)->toStartWith('%PDF');
    });

    it('renders the sections in the layout order (modern + compact)', function (string $template) use ($data): void {
        $reordered = array_merge($data, [
            'sections' => [
                'skills' => ['visible' => true, 'order' => 0],
                'header' => ['visible' => true, 'order' => 1],
                'experience' => ['visible' => true, 'order' => 2],
                'about_me' => ['visible' => true, 'order' => 3],
                'contacts' => ['visible' => true, 'order' => 4],
            ],
        ]);

        $html = view("resume-templates.{$template}", ['data' => $reordered, 'locale' => 'en'])->render();

        $markers = [
            'skills' => strpos($html, '>Skills</h2>'),
            'header' => strpos($html, 'Alex Kovalev'),
            'experience' => strpos($html, '>Work Experience</h2>'),
            'about_me' => strpos($html, '>About Me</h2>'),
            'contacts' => strpos($html, '>Contacts</h2>'),
        ];

        foreach ($markers as $section => $position) {
            expect($position)->not->toBeFalse("{$section} section missing from the {$template} output");
        }

        expect($markers['skills'])->toBeLessThan($markers['header'])
            ->and($markers['header'])->toBeLessThan($markers['experience'])
            ->and($markers['experience'])->toBeLessThan($markers['about_me'])
            ->and($markers['about_me'])->toBeLessThan($markers['contacts']);
    })->with(['modern', 'compact']);

    it('renders the classic columns in their relative layout order', function () use ($data): void {
        $reordered = array_merge($data, [
            'sections' => [
                'header' => ['visible' => true, 'order' => 4],
                'contacts' => ['visible' => true, 'order' => 0],
                'experience' => ['visible' => true, 'order' => 1],
                'skills' => ['visible' => true, 'order' => 2],
                'about_me' => ['visible' => true, 'order' => 3],
            ],
        ]);

        $html = view('resume-templates.classic', ['data' => $reordered, 'locale' => 'en'])->render();

        $sidebarContacts = strpos($html, '>Contacts</h2>');
        $sidebarSkills = strpos($html, '>Skills</h2>');
        $mainExperience = strpos($html, '>Work Experience</h2>');
        $mainAbout = strpos($html, '>About Me</h2>');

        expect($sidebarContacts)->not->toBeFalse()
            ->and($sidebarSkills)->not->toBeFalse()
            ->and($mainExperience)->not->toBeFalse()
            ->and($mainAbout)->not->toBeFalse()
            // Sidebar: contacts (order 0) before skills (order 2); main:
            // experience (order 1) before about (order 3) — the fixed
            // two-column split keeps the relative order within each column.
            ->and($sidebarContacts)->toBeLessThan($sidebarSkills)
            ->and($mainExperience)->toBeLessThan($mainAbout);
    });
});
