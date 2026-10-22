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

    /**
     * Text drawing positions from dompdf's CPDF output: content pushed below
     * the page box (negative y) is clipped in the rendered PDF.
     */
    $textRuns = function (string $pdf): array {
        preg_match_all('#stream\r?\n(.*?)\r?\nendstream#s', $pdf, $streams);

        $runs = [];

        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream);

            if ($content === false) {
                continue;
            }

            preg_match_all('/([-0-9.]+) ([-0-9.]+) Td[^\n]*\[\((.*?)\)\]\s*TJ/s', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $runs[] = [
                    'x' => (float) $match[1],
                    'y' => (float) $match[2],
                    'text' => mb_convert_encoding($match[3], 'UTF-8', 'UTF-16BE'),
                ];
            }
        }

        return $runs;
    };

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

    it('renders one proficiency-labelled line per skill by default', function () use ($data): void {
        $html = view('resume-templates.modern', ['data' => $data, 'locale' => 'en'])->render();

        expect($html)->toContain('<div class="skill-group-name">Backend</div>')
            ->toContain('Laravel')
            ->toContain('Expert');
    });

    it('renders the skills inline after the group name without proficiency', function () use ($data): void {
        $inline = array_merge($data, [
            'options' => ['skills_layout' => 'inline'],
            'skills' => [[
                'group' => 'Backend',
                'skills' => [
                    ['name' => 'Laravel', 'proficiency' => 5, 'proficiency_label' => 'Expert'],
                    ['name' => 'PHP', 'proficiency' => 4, 'proficiency_label' => 'Advanced'],
                ],
            ]],
        ]);

        $html = view('resume-templates.modern', ['data' => $inline, 'locale' => 'en'])->render();

        expect($html)->toContain('<span class="skill-group-name">Backend:</span>')
            ->toContain('Laravel, PHP')
            ->not->toContain('Expert')
            ->not->toContain('Advanced');
    });

    it('flushes the compact heading that directly follows the header', function () use ($data): void {
        $html = view('resume-templates.compact', ['data' => $data, 'locale' => 'en'])->render();

        expect($html)->toContain('<h2 class="flush">About Me</h2>');
    });

    it('right-aligns the compact experience period', function () use ($data, $textRuns): void {
        $runs = $textRuns(app(ResumePdfRenderer::class)->render('compact', 'en', $data));

        $period = collect($runs)->first(
            fn (array $run): bool => str_contains($run['text'], '2023 – Present'),
        );
        $position = collect($runs)->first(
            fn (array $run): bool => str_contains($run['text'], 'Senior Engineer'),
        );

        expect($period)->not->toBeNull()
            ->and($position)->not->toBeNull()
            ->and($period['x'])->toBeGreaterThan($position['x'] + 200);
    });

    it('keeps the compact heading spacing after a heading-less contacts block', function () use ($data): void {
        $contactsFirst = array_merge($data, [
            'options' => ['contacts_show_heading' => false],
            'sections' => [
                'header' => ['visible' => true, 'order' => 0],
                'contacts' => ['visible' => true, 'order' => 1],
                'about_me' => ['visible' => true, 'order' => 2],
                'skills' => ['visible' => false, 'order' => 3],
                'experience' => ['visible' => false, 'order' => 4],
            ],
        ]);

        $html = view('resume-templates.compact', ['data' => $contactsFirst, 'locale' => 'en'])->render();

        expect($html)->toContain('<h2 class="">About Me</h2>');
    });

    it('hides the contacts heading when disabled', function () use ($data): void {
        $hidden = array_merge($data, [
            'options' => ['contacts_show_heading' => false],
        ]);

        $html = view('resume-templates.modern', ['data' => $hidden, 'locale' => 'en'])->render();

        expect($html)->toContain('GitHub')
            ->not->toContain('>Contacts</h2>');
    });

    it('renders contacts in two columns filled row by row', function () use ($data): void {
        $twoColumns = array_merge($data, [
            'options' => ['contacts_layout' => 'two_columns'],
            'contacts' => [
                ['name' => 'GitHub', 'value' => 'gh', 'icon' => null, 'url' => null],
                ['name' => 'Email', 'value' => 'mail', 'icon' => null, 'url' => null],
                ['name' => 'Phone', 'value' => 'phone', 'icon' => null, 'url' => null],
            ],
        ]);

        $html = view('resume-templates.modern', ['data' => $twoColumns, 'locale' => 'en'])->render();
        $table = substr($html, (int) strpos($html, '<table class="contacts">'));

        expect($table)->toContain('GitHub')
            ->and(substr_count($table, '<td'))->toBe(4)
            ->and(strpos($table, 'GitHub'))->toBeLessThan((int) strpos($table, 'Email'))
            ->and(strpos($table, 'Email'))->toBeLessThan((int) strpos($table, 'Phone'));
    });

    it('renders the per-experience skill tags only when enabled', function () use ($data): void {
        $experience = array_merge($data, [
            'skills' => [],
            'experience' => [[
                'position' => 'Senior Engineer',
                'company_name' => 'Acme Inc',
                'company_country' => 'Germany',
                'started_at' => '2023-01-15',
                'ended_at' => null,
                'achievements' => [],
                'tech_tags' => ['Kubernetes'],
            ]],
        ]);
        $withoutTags = array_merge($experience, [
            'options' => ['experience_show_skills' => false],
        ]);

        $enabledHtml = view('resume-templates.compact', ['data' => $experience, 'locale' => 'en'])->render();
        $disabledHtml = view('resume-templates.compact', ['data' => $withoutTags, 'locale' => 'en'])->render();

        expect($enabledHtml)->toContain('Kubernetes')
            ->and($disabledHtml)->not->toContain('Kubernetes');
    });

    it('keeps the first classic sidebar heading flush when the contacts heading is hidden', function () use ($data): void {
        $reordered = array_merge($data, [
            'options' => ['contacts_show_heading' => false],
            'sections' => [
                'contacts' => ['visible' => true, 'order' => 0],
                'skills' => ['visible' => true, 'order' => 2],
                'header' => ['visible' => true, 'order' => 4],
                'experience' => ['visible' => true, 'order' => 1],
                'about_me' => ['visible' => true, 'order' => 3],
            ],
        ]);

        $html = view('resume-templates.classic', ['data' => $reordered, 'locale' => 'en'])->render();

        expect($html)->not->toContain('>Contacts</h2>')
            ->toContain('<h2 class="first">Skills</h2>');
    });

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

    it('keeps the classic main column at full width beside the sidebar', function () use ($data, $textRuns): void {
        $skills = array_map(
            fn (string $group): array => [
                'group' => $group,
                'skills' => [
                    ['name' => 'PHP', 'proficiency' => 5, 'proficiency_label' => 'Expert'],
                    ['name' => 'Laravel', 'proficiency' => 5, 'proficiency_label' => 'Expert'],
                    ['name' => 'Node.js', 'proficiency' => 4, 'proficiency_label' => 'Advanced'],
                    ['name' => 'Redis', 'proficiency' => 4, 'proficiency_label' => 'Advanced'],
                ],
            ],
            ['Backend', 'Frontend', 'DevOps', 'DB and Queues', 'Tools', 'AI'],
        );
        $longAbout = 'I craft fast, accessible web applications end to end — from PostgreSQL-backed '
            .'Laravel APIs to Vue interfaces that feel instant. I care about clean architecture, '
            .'strict domain boundaries and tests that document behavior.';
        $withSidebar = array_merge($data, ['about' => $longAbout, 'skills' => $skills]);
        $withoutSidebar = array_merge($withSidebar, [
            'sections' => array_merge($data['sections'], [
                'skills' => ['visible' => false, 'order' => 2],
                'contacts' => ['visible' => false, 'order' => 4],
            ]),
        ]);

        $aboutRun = function (string $pdf) use ($textRuns): ?array {
            return collect($textRuns($pdf))->first(
                fn (array $run): bool => str_contains($run['text'], 'I craft fast'),
            );
        };

        $besideSidebar = $aboutRun(app(ResumePdfRenderer::class)->render('classic', 'en', $withSidebar));
        $withoutSidebarRun = $aboutRun(app(ResumePdfRenderer::class)->render('classic', 'en', $withoutSidebar));

        expect($besideSidebar)->not->toBeNull()
            ->and($withoutSidebarRun)->not->toBeNull()
            ->and($besideSidebar['x'])->toBe($withoutSidebarRun['x']);
    });

    it('paginates the classic two-column layout without clipping content', function () use ($data, $textRuns): void {
        $experience = [];

        for ($index = 1; $index <= 30; $index++) {
            $experience[] = [
                'position' => "Position {$index}",
                'company_name' => 'Acme Inc',
                'company_country' => 'Germany',
                'started_at' => '2023-01-15',
                'ended_at' => null,
                'achievements' => [['description' => "Achievement {$index}", 'project_link' => null]],
                'tech_tags' => [],
            ];
        }

        $pdf = app(ResumePdfRenderer::class)->render(
            'classic',
            'en',
            array_merge($data, ['experience' => $experience]),
        );
        $runs = $textRuns($pdf);

        $lastItem = collect($runs)->first(
            fn (array $run): bool => str_contains($run['text'], 'Position 30'),
        );

        expect($lastItem)->not->toBeNull()
            ->and($lastItem['y'])->toBeGreaterThanOrEqual(0)
            ->and(collect($runs)->every(fn (array $run): bool => $run['y'] >= 0))->toBeTrue();
    });
});
