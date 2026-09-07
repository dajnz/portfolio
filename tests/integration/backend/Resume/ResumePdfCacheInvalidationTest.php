<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Resume;

use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeResumePdfRenderer;
use Tests\Support\Traits\SeedsResumeData;
use Tests\Support\Traits\SerializesResumePdfCache;

uses(SeedsResumeData::class, SerializesResumePdfCache::class);

beforeEach(function (): void {
    $this->lockResumePdfCache();
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
    Cache::store('redis')->flush();

    $this->renderer = new FakeResumePdfRenderer();
    app()->instance(ResumePdfRendererInterface::class, $this->renderer);

    $this->seedResumeConfig(['mode' => 'auto']);
    $this->seedResumeSourceData();
});

afterEach(function (): void {
    $this->unlockResumePdfCache();
});

describe('resume PDF cache invalidation', function (): void {
    it('invalidates the cached paths when a source row is saved', function (): void {
        $this->get('/cv')->assertOk();

        $file = Storage::disk('rustfs')->files('about')[0];
        $cacheKey = 'resume:pdf:en:'.substr(basename($file), 10, 64);
        expect(Cache::store('redis')->has($cacheKey))->toBeTrue();

        $skill = SkillModel::query()->first();
        $skill->update(['name' => 'Laravel 12']);

        expect(Cache::store('redis')->has($cacheKey))->toBeFalse();
    });

    it('invalidates the cached paths when a source row is deleted', function (): void {
        $this->get('/cv')->assertOk();

        $file = Storage::disk('rustfs')->files('about')[0];
        $cacheKey = 'resume:pdf:en:'.substr(basename($file), 10, 64);

        // Instance delete — query-builder deletes fire no model events.
        $contact = ContactModel::query()->first();
        $contact->delete();

        expect(Cache::store('redis')->has($cacheKey))->toBeFalse();
    });
});
