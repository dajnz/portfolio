<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use App\Features\Resume\Infra\Models\ResumeConfigModel;
use App\Features\Resume\Infra\Models\ResumeConfigTranslationModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;
use Tests\Support\Traits\SeedsResumeData;
use Tests\Support\Traits\SerializesResumePdfCache;

uses(InteractsWithAdmin::class, SeedsResumeData::class, SerializesResumePdfCache::class);

beforeEach(function (): void {
    $this->lockResumePdfCache();
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
    Cache::store('redis')->flush();

    $this->renderer = new \Tests\Support\Fakes\FakeResumePdfRenderer();
    app()->instance(\App\Features\Resume\App\Contracts\ResumePdfRendererInterface::class, $this->renderer);
});

afterEach(function (): void {
    $this->unlockResumePdfCache();
});

describe('ResumeController admin', function (): void {
    beforeEach(function (): void {
        $this->loginAsAdmin($this->createAdmin());
    });

    it('renders the edit page with the config values', function (): void {
        $this->seedResumeConfig(['mode' => 'auto']);
        DB::table('resume_config_translations')->insert([
            ['resume_config_id' => 1, 'locale' => 'en', 'about_text' => 'Custom about.'],
            ['resume_config_id' => 1, 'locale' => 'ru', 'about_text' => 'Своё «обо мне».'],
        ]);

        $this->get('/admin/resume')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resume/Admin/Pages/ResumeEditPage')
                ->where('mode', 'auto')
                ->where('layout.template', 'modern')
                ->where('layout.sections.contacts.order', 4)
                ->where('custom_resume_file', null)
                ->where('about_texts.en', 'Custom about.')
                ->where('about_texts.ru', 'Своё «обо мне».'));
    });

    it('carries the inclusion-summary counts (included items only, trashed excluded)', function (): void {
        $this->seedResumeConfig(['mode' => 'auto']);
        $this->seedResumeSourceData();

        $groupId = DB::table('skill_groups')->insertGetId(['sort_order' => 1, 'icon' => 'heroicons:server']);
        DB::table('skills')->insert([
            'skill_group_id' => $groupId,
            'name' => 'Vue',
            'icon' => 'devicon:vuejs',
            'proficiency' => 4,
            'include_in_resume' => false,
            'sort_order' => 0,
        ]);
        DB::table('contacts')->insert([
            'value' => 'dev@example.com',
            'icon' => 'heroicons:envelope',
            'url' => null,
            'include_in_resume' => false,
            'sort_order' => 1,
        ]);
        DB::table('experience_timeline_items')->insert([
            'started_at' => '2020-01-01',
            'ended_at' => '2022-01-01',
            'is_published' => true,
            'sort_order' => 1,
            'include_in_resume' => true,
            'deleted_at' => now(),
        ]);

        $this->get('/admin/resume')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resume/Admin/Pages/ResumeEditPage')
                ->where('included_skills_count', 1)
                ->where('included_experience_count', 1)
                ->where('included_contacts_count', 1));
    });

    it('renders the documented defaults when no config row exists', function (): void {
        $this->get('/admin/resume')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resume/Admin/Pages/ResumeEditPage')
                ->where('mode', 'manual')
                ->where('layout.template', 'modern')
                ->where('layout.sections.header.order', 0)
                ->where('layout.skills_layout', 'per_line')
                ->where('layout.contacts_show_heading', true)
                ->where('layout.contacts_layout', 'per_line')
                ->where('layout.experience_show_skills', true)
                ->where('custom_resume_file', null)
                ->where('about_texts', []));
    });

    it('persists the mode, layout and per-locale about texts', function (): void {
        $this->seedResumeConfig(['mode' => 'manual']);

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => [
                'template' => 'classic',
                'sections' => [
                    'header' => ['visible' => true, 'order' => 0],
                    'about_me' => ['visible' => true, 'order' => 1],
                    'skills' => ['visible' => false, 'order' => 2],
                    'experience' => ['visible' => true, 'order' => 3],
                    'contacts' => ['visible' => true, 'order' => 4],
                ],
                'skills_layout' => 'inline',
                'contacts_show_heading' => false,
                'contacts_layout' => 'two_columns',
                'experience_show_skills' => false,
            ],
            'about_text' => ['en' => 'Custom about.', 'ru' => ''],
        ])->assertRedirect();

        $config = ResumeConfigModel::query()->find(1);

        expect($config->mode)->toBe('auto')
            ->and($config->layout['template'])->toBe('classic')
            ->and($config->layout['sections']['skills']['visible'])->toBeFalse()
            ->and($config->layout['skills_layout'])->toBe('inline')
            ->and($config->layout['contacts_show_heading'])->toBeFalse()
            ->and($config->layout['contacts_layout'])->toBe('two_columns')
            ->and($config->layout['experience_show_skills'])->toBeFalse()
            ->and(ResumeConfigTranslationModel::query()->where('locale', 'en')->value('about_text'))->toBe('Custom about.')
            ->and(ResumeConfigTranslationModel::query()->where('locale', 'ru')->value('about_text'))->toBeNull();
    });

    it('persists the manual file path', function (): void {
        $this->put('/admin/resume', [
            'mode' => 'manual',
            'layout' => self::DEFAULT_LAYOUT,
            'custom_resume_file' => 'about/custom-resume.pdf',
            'about_text' => [],
        ])->assertRedirect();

        expect(ResumeConfigModel::query()->find(1)->custom_resume_file)->toBe('about/custom-resume.pdf');
    });

    it('rejects an invalid mode', function (): void {
        $this->put('/admin/resume', [
            'mode' => 'pdf',
            'layout' => self::DEFAULT_LAYOUT,
        ])->assertSessionHasErrors('mode');
    });

    it('rejects an invalid template', function (): void {
        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => ['template' => 'fancy', 'sections' => self::DEFAULT_LAYOUT['sections']],
        ])->assertSessionHasErrors('layout.template');
    });

    it('rejects a section order outside 0-4', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        $layout['sections']['contacts']['order'] = 5;

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors('layout.sections.contacts.order');
    });

    it('rejects a missing section', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        unset($layout['sections']['contacts']);

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors('layout.sections.contacts');
    });

    it('rejects a non-boolean section visibility', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        $layout['sections']['skills']['visible'] = 'yes';

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors('layout.sections.skills.visible');
    });

    it('rejects an invalid skills layout', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        $layout['skills_layout'] = 'grid';

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors('layout.skills_layout');
    });

    it('rejects an invalid contacts layout', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        $layout['contacts_layout'] = 'grid';

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors('layout.contacts_layout');
    });

    it('rejects non-boolean rendering flags', function (): void {
        $layout = self::DEFAULT_LAYOUT;
        $layout['contacts_show_heading'] = 'yes';
        $layout['experience_show_skills'] = 'no';

        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => $layout,
        ])->assertSessionHasErrors([
            'layout.contacts_show_heading',
            'layout.experience_show_skills',
        ]);
    });

    it('requires the custom file path in manual mode', function (): void {
        $this->put('/admin/resume', [
            'mode' => 'manual',
            'layout' => self::DEFAULT_LAYOUT,
            'custom_resume_file' => null,
        ])->assertSessionHasErrors('custom_resume_file');
    });

    it('rejects an over-long about text', function (): void {
        $this->put('/admin/resume', [
            'mode' => 'auto',
            'layout' => self::DEFAULT_LAYOUT,
            'about_text' => ['en' => str_repeat('a', 1001)],
        ])->assertSessionHasErrors('about_text.en');
    });
});

describe('ResumeController auth', function (): void {
    it('redirects unauthenticated admin requests to the login page', function (): void {
        $this->get('/admin/resume')->assertRedirect(route('login'));
        $this->put('/admin/resume', ['mode' => 'auto', 'layout' => self::DEFAULT_LAYOUT])->assertRedirect(route('login'));
    });
});

describe('ResumeController regenerate', function (): void {
    it('requires authentication', function (): void {
        $this->postJson('/api/resume/regenerate')->assertUnauthorized();
    });

    it('clears the resume cache keys and deletes the generated PDFs', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        Cache::store('redis')->put('resume:pdf:en:abc123', 'about/resume_en_abc123.pdf');
        Cache::store('redis')->put('resume:pdf:ru:abc123', 'about/resume_ru_abc123.pdf');
        Storage::disk('rustfs')->put('about/resume_en_abc123.pdf', '%PDF-1.7');
        Storage::disk('rustfs')->put('about/resume_ru_abc123.pdf', '%PDF-1.7');
        Storage::disk('rustfs')->put('about/photo.jpg', 'photo');

        $this->postJson('/api/resume/regenerate')
            ->assertOk()
            ->assertJsonPath('data.message', 'Resume PDF regenerated.');

        expect(Cache::store('redis')->has('resume:pdf:en:abc123'))->toBeFalse()
            ->and(Cache::store('redis')->has('resume:pdf:ru:abc123'))->toBeFalse()
            ->and(Storage::disk('rustfs')->exists('about/resume_en_abc123.pdf'))->toBeFalse()
            ->and(Storage::disk('rustfs')->exists('about/resume_ru_abc123.pdf'))->toBeFalse()
            ->and(Storage::disk('rustfs')->exists('about/photo.jpg'))->toBeTrue();
    });
});

describe('ResumeController regenerate — regeneration flow', function (): void {
    it('regenerates the PDF on the next /cv request', function (): void {
        $this->loginAsAdmin($this->createAdmin());
        $this->seedResumeConfig(['mode' => 'auto']);
        $this->seedResumeSourceData();

        $this->get('/cv')->assertOk();
        $file = Storage::disk('rustfs')->files('about')[0];

        $this->renderer->reset();

        $this->postJson('/api/resume/regenerate')->assertOk();
        $this->get('/cv')->assertOk();

        // The old path was deleted and the next request regenerated it
        // (same content hash → same path) — the renderer ran exactly once.
        expect($this->renderer->rendered)->toHaveCount(1)
            ->and(Storage::disk('rustfs')->exists($file))->toBeTrue();
    });
});
