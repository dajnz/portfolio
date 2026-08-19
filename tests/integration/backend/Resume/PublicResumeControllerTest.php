<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeResumePdfRenderer;
use Tests\Support\Traits\SeedsResumeData;

uses(SeedsResumeData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
    Cache::store('redis')->flush();

    $this->renderer = new FakeResumePdfRenderer();
    app()->instance(ResumePdfRendererInterface::class, $this->renderer);
});

describe('PublicResumeController /cv — auto mode', function (): void {
    beforeEach(function (): void {
        $this->seedResumeConfig(['mode' => 'auto']);
        $this->seedResumeSourceData();
    });

    it('generates and serves the PDF on first request, caching the path', function (): void {
        $this->get('/cv')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="resume.pdf"');

        expect($this->renderer->rendered)->toHaveCount(1)
            ->and($this->renderer->rendered[0]['template'])->toBe('modern')
            ->and($this->renderer->rendered[0]['locale'])->toBe('en');

        $file = Storage::disk('rustfs')->files('about');

        expect($file)->toHaveCount(1)
            ->and($file[0])->toMatch('#^about/resume_en_[a-f0-9]{64}\.pdf$#')
            ->and(Storage::disk('rustfs')->get($file[0]))->toBe('%PDF-1.7 fake-resume');

        $cacheKey = 'resume:pdf:en:'.substr(basename($file[0]), 10, 64);
        expect(Cache::store('redis')->get($cacheKey))->toBe($file[0]);
    });

    it('serves from the cache on subsequent requests without regenerating', function (): void {
        $this->get('/cv')->assertOk();
        $this->renderer->reset();

        $this->get('/cv')->assertOk();

        expect($this->renderer->rendered)->toHaveCount(0);
    });

    it('reuses an existing RustFS file when the cache is empty (backup-restore scenario)', function (): void {
        $this->get('/cv')->assertOk();
        Cache::store('redis')->flush();
        $this->renderer->reset();

        $this->get('/cv')->assertOk();

        expect($this->renderer->rendered)->toHaveCount(0)
            ->and(Storage::disk('rustfs')->files('about'))->toHaveCount(1);
    });

    it('honors the ?locale= query parameter for the generation', function (): void {
        $this->get('/cv?locale=ru')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($this->renderer->rendered[0]['locale'])->toBe('ru')
            ->and(Storage::disk('rustfs')->files('about')[0])->toMatch('#^about/resume_ru_[a-f0-9]{64}\.pdf$#');
    });

    it('falls back to the cookie locale for an invalid ?locale=', function (): void {
        $this->withUnencryptedCookie('locale', 'ru');

        $this->get('/cv?locale=xx')->assertOk();

        expect($this->renderer->rendered[0]['locale'])->toBe('ru');
    });

    it('serves the locale-prefixed path variant', function (): void {
        $this->get('/ru/cv')->assertOk();

        expect($this->renderer->rendered[0]['locale'])->toBe('ru');
    });

    it('assembles the resume data with the about fallback and the include filters', function (): void {
        $this->get('/cv')->assertOk();

        $data = $this->renderer->rendered[0]['data'];

        expect($data['header'])->toBe(['name' => 'Alex Kovalev', 'title' => 'Full-Stack Developer'])
            ->and($data['about'])->toBe('I build fast web apps.')
            ->and($data['skills'][0]['group'])->toBe('Backend')
            ->and($data['skills'][0]['skills'][0]['name'])->toBe('Laravel')
            ->and($data['experience'][0]['position'])->toBe('Senior Engineer')
            ->and($data['experience'][0]['company_name'])->toBe('Acme Inc')
            ->and($data['experience'][0]['tech_tags'])->toBe(['Laravel'])
            ->and($data['contacts'][0]['name'])->toBe('GitHub')
            ->and($data['contacts'][0]['url'])->toBe('https://github.com/alex');
    });

    it('uses the custom about text and excludes non-included skills and hidden sections', function (): void {
        DB::table('resume_config_translations')->insert([
            'resume_config_id' => 1, 'locale' => 'en', 'about_text' => 'Custom about me.',
        ]);

        SkillModel::query()->create([
            'skill_group_id' => (int) DB::table('skill_groups')->value('id'),
            'name' => 'Excluded',
            'icon' => 'devicon:php',
            'proficiency' => 3,
            'include_in_resume' => false,
            'sort_order' => 1,
        ]);

        $layout = self::DEFAULT_LAYOUT;
        $layout['sections']['contacts']['visible'] = false;
        DB::table('resume_config')->where('id', 1)->update([
            'layout' => json_encode($layout),
        ]);

        $this->get('/cv')->assertOk();

        $data = $this->renderer->rendered[0]['data'];

        expect($data['about'])->toBe('Custom about me.')
            ->and($data['skills'][0]['skills'])->toHaveCount(1)
            ->and($data['contacts'])->toBe([]);
    });
});
