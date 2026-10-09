<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;
use DateTimeZone;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('AboutController', function (): void {
    it('requires authentication for the edit page', function (): void {
        $this->get('/admin/about')->assertRedirect('/admin/login');
    });

    it('renders the edit form with per-locale maps', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        AboutInfoModel::factory()->create(['id' => 1]);
        AboutInfoTranslationModel::query()->insert([
            ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex', 'title' => 'Dev', 'short_pitch' => 'Pitch.', 'location' => 'Berlin'],
            ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс', 'title' => 'Разработчик', 'short_pitch' => 'Описание.', 'location' => null],
        ]);

        $this->get('/admin/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/AboutEditPage')
                ->where('about.name.en', 'Alex')
                ->where('about.name.ru', 'Алекс')
                ->where('about.title.en', 'Dev')
                ->where('about.short_pitch.ru', 'Описание.')
                ->where('about.location.en', 'Berlin')
                ->where('about.location.ru', null)
                ->where('about.show_location', true)
                ->where('about.work_type', 'remote')
                ->where('timezones', DateTimeZone::listIdentifiers())
                ->where('resume_mode', 'manual'));
    });

    it('exposes the resume_config mode for the resume summary line', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        \Illuminate\Support\Facades\DB::table('resume_config')->insert([
            'id' => 1,
            'mode' => 'auto',
            'layout' => json_encode([
                'template' => 'modern',
                'sections' => [
                    'header' => ['visible' => true, 'order' => 0],
                    'about_me' => ['visible' => true, 'order' => 1],
                    'skills' => ['visible' => true, 'order' => 2],
                    'experience' => ['visible' => true, 'order' => 3],
                    'contacts' => ['visible' => true, 'order' => 4],
                ],
            ]),
        ]);

        $this->get('/admin/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/AboutEditPage')
                ->where('resume_mode', 'auto'));
    });

    it('updates the about row and its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => 'Alex', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'location' => ['en' => 'Berlin', 'ru' => 'Берлин'],
            'photo' => 'about/photo.jpg',
            'photo_alt' => ['en' => 'Portrait', 'ru' => null],
            'show_location' => true,
            'timezone' => 'Europe/Berlin',
            'relocation_readiness' => true,
            'availability_status' => 'limited',
            'work_type' => 'hybrid',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = AboutInfoModel::query()->find(1);
        expect($row)->not->toBeNull()
            ->and($row->photo)->toBe('about/photo.jpg')
            ->and($row->photo_alt)->toBe(['en' => 'Portrait'])
            ->and($row->show_location)->toBeTrue()
            ->and($row->timezone)->toBe('Europe/Berlin')
            ->and($row->relocation_readiness)->toBeTrue()
            ->and($row->availability_status)->toBe('limited')
            ->and($row->work_type)->toBe('hybrid');

        expect(AboutInfoTranslationModel::query()->count())->toBe(2)
            ->and(AboutInfoTranslationModel::query()->where('locale', 'en')->first()->name)->toBe('Alex')
            ->and(AboutInfoTranslationModel::query()->where('locale', 'ru')->first()->location)->toBe('Берлин');
    });

    it('rejects a missing per-locale name and invalid enums', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => '', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'timezone' => 'Europe/Berlin',
            'availability_status' => 'someday',
            'work_type' => 'onsite',
        ])->assertRedirect()
            ->assertSessionHasErrors(['name.en', 'availability_status', 'work_type']);
    });

    it('requires a per-locale location and bounds the photo alt text', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => 'Alex', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'timezone' => 'Europe/Berlin',
            'availability_status' => 'available',
            'work_type' => 'remote',
            'photo_alt' => ['en' => str_repeat('a', 501)],
        ])->assertRedirect()
            ->assertSessionHasErrors(['location.en', 'location.ru', 'photo_alt.en']);
    });

    it('rejects a non-timezone value', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => 'Alex', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'timezone' => 'Not/AZone',
            'availability_status' => 'available',
            'work_type' => 'remote',
        ])->assertRedirect()
            ->assertSessionHasErrors('timezone');
    });
});
