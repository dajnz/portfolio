<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Resume\Infra\Models\ResumeConfigModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Seeds the single-row configuration entities: the `about_info` row
 * (id = 1) with the demo landing profile — the persona, bilingual
 * `en`/`ru` translations and the demo photo — and the `resume_config`
 * row (id = 1, `mode = 'manual'`, the default `layout` JSON). Must never
 * touch the `admins` table.
 *
 * The about state is refreshed on every run (same semantics as
 * SettingsSeeder) so the demo profile is deterministic: the row and its
 * translations are upserted, and the demo photo stub is (re)uploaded to
 * RustFS under a fixed key. Test runs (phpunit) never touch the object
 * storage — they seed the row without a photo; when RustFS is
 * unreachable the row is seeded with `photo = null` and a warning is
 * logged instead of failing the seed.
 */
final class SingleRowEntitySeeder extends Seeder
{
    /** Demo persona shared by the dev and e2e datasets. */
    private const ABOUT_TRANSLATIONS = [
        'en' => [
            'name' => 'Ivan Ivanov',
            'title' => 'Full-Stack Developer',
            'short_pitch' => "I craft fast, accessible web applications end to end — from PostgreSQL-backed Laravel APIs to Vue interfaces that feel instant.\n\nI care about clean architecture, strict domain boundaries and tests that document behavior, so features ship without regressions.\nAway from the keyboard I write about backend performance, bilingual UX and the tooling that keeps a solo project maintainable.\n\nGlory to the robots!",
            'location' => 'Berlin',
        ],
        'ru' => [
            'name' => 'Иван Иванов',
            'title' => 'Фулстек-разработчик',
            'short_pitch' => "Я создаю быстрые и доступные веб-приложения целиком — от API на Laravel и PostgreSQL до интерфейсов на Vue, которые откликаются мгновенно.\n\nЦеню чистую архитектуру, строгие границы домена и тесты, которые документируют поведение, чтобы новые функции выходили без регрессий.\nВне клавиатуры пишу о производительности бэкенда, двуязычном UX и инструментах, которые держат одиночный проект поддерживаемым.\n\nСлава роботам!",
            'location' => 'Берлин',
        ],
    ];

    private const DEMO_PHOTO_PATH = 'about/demo-avatar.svg';

    public function run(): void
    {
        $this->seedAboutInfo();

        $this->seedResumeConfig();
    }

    private function seedAboutInfo(): void
    {
        $photo = $this->provisionDemoPhoto();

        DB::table('about_info')->updateOrInsert(
            ['id' => 1],
            [
                'photo' => $photo,
                'photo_alt' => $photo === null ? null : json_encode([
                    'en' => 'Portrait of Ivan Ivanov',
                    'ru' => 'Портрет Ивана Иванова',
                ], JSON_UNESCAPED_UNICODE),
                'show_location' => true,
                'timezone' => 'Europe/Berlin',
                'relocation_readiness' => true,
                'availability_status' => 'available',
                'work_type' => 'hybrid',
            ],
        );

        foreach (self::ABOUT_TRANSLATIONS as $locale => $values) {
            DB::table('about_info_translations')->updateOrInsert(
                ['about_info_id' => 1, 'locale' => $locale],
                $values,
            );
        }
    }

    /**
     * Uploads the committed demo-avatar stub to RustFS under a fixed key
     * (the row's `photo` references a `/assets/`-served object, so the
     * image must actually exist for the landing photo to render).
     * Returns the stored key, or null when the upload is not possible
     * (unit tests) or fails (RustFS unreachable) — the about row is then
     * seeded photo-less and the public surface shows its placeholder.
     */
    private function provisionDemoPhoto(): ?string
    {
        if (App::runningUnitTests()) {
            return null;
        }

        $source = App::databasePath('seeders/assets/demo-avatar.svg');

        try {
            Storage::disk('rustfs')->put(
                self::DEMO_PHOTO_PATH,
                (string) file_get_contents($source),
                ['ContentType' => 'image/svg+xml'],
            );
        } catch (Throwable $exception) {
            Log::warning('Could not upload the demo about photo to RustFS; seeding without a photo.', [
                'path' => self::DEMO_PHOTO_PATH,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return self::DEMO_PHOTO_PATH;
    }

    private function seedResumeConfig(): void
    {
        ResumeConfigModel::query()->updateOrCreate(
            ['id' => 1],
            [
                'mode' => 'manual',
                'layout' => [
                    'template' => 'modern',
                    'sections' => [
                        'header' => ['visible' => true, 'order' => 0],
                        'about_me' => ['visible' => true, 'order' => 1],
                        'skills' => ['visible' => true, 'order' => 2],
                        'experience' => ['visible' => true, 'order' => 3],
                        'contacts' => ['visible' => true, 'order' => 4],
                    ],
                    'skills_layout' => 'per_line',
                    'contacts_show_heading' => true,
                    'contacts_layout' => 'per_line',
                    'experience_show_skills' => true,
                ],
                'custom_resume_file' => null,
            ],
        );
    }
}
