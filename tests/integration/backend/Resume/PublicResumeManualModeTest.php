<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
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

describe('PublicResumeController /cv — manual mode', function (): void {
    it('serves the uploaded file', function (): void {
        $this->seedResumeConfig([
            'mode' => 'manual',
            'custom_resume_file' => 'about/custom-resume.pdf',
        ]);
        Storage::disk('rustfs')->put('about/custom-resume.pdf', '%PDF-1.7 manual');

        $this->get('/cv')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($this->renderer->rendered)->toHaveCount(0);
    });

    it('404s when the manual file is missing', function (): void {
        $this->seedResumeConfig(['mode' => 'manual', 'custom_resume_file' => null]);

        $this->get('/cv')->assertNotFound();
    });

    it('404s when no config row exists', function (): void {
        $this->get('/cv')->assertNotFound();
    });
});
