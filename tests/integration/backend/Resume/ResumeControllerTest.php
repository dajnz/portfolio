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

uses(InteractsWithAdmin::class, SeedsResumeData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
    Cache::store('redis')->flush();

    $this->renderer = new \Tests\Support\Fakes\FakeResumePdfRenderer();
    app()->instance(\App\Features\Resume\App\Contracts\ResumePdfRendererInterface::class, $this->renderer);
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

    it('renders the documented defaults when no config row exists', function (): void {
        $this->get('/admin/resume')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resume/Admin/Pages/ResumeEditPage')
                ->where('mode', 'manual')
                ->where('layout.template', 'modern')
                ->where('layout.sections.header.order', 0)
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
            ],
            'about_text' => ['en' => 'Custom about.', 'ru' => ''],
        ])->assertRedirect();

        $config = ResumeConfigModel::query()->find(1);

        expect($config->mode)->toBe('auto')
            ->and($config->layout['template'])->toBe('classic')
            ->and($config->layout['sections']['skills']['visible'])->toBeFalse()
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
