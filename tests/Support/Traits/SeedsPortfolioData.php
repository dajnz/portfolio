<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixture seeding for the Portfolio test suites: the full landing
 * dataset (about + skills + contacts + featured projects) and the raw
 * project-row helpers used by the landing and project-read tests.
 */
trait SeedsPortfolioData
{
    /**
     * Seeds a full landing dataset (about, skills, contacts, featured
     * projects).
     */
    protected function seedLandingDataset(): void
    {
        AboutInfoModel::factory()->create([
            'id' => 1,
            'photo' => 'about/photo.jpg',
            'photo_alt' => ['en' => 'Portrait', 'ru' => 'Портрет'],
            'availability_status' => 'available',
            'work_type' => 'hybrid',
            'show_location' => true,
            'timezone' => 'Europe/Berlin',
            'relocation_readiness' => true,
        ]);
        AboutInfoTranslationModel::query()->insert([
            ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex Kovalev', 'title' => 'Full-Stack Developer', 'short_pitch' => 'I build fast web apps.', 'location' => 'Berlin'],
            ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс Ковалев', 'title' => 'Фулстек-разработчик', 'short_pitch' => 'Я создаю быстрые веб-приложения.', 'location' => 'Берлин'],
        ]);

        $group = SkillGroupModel::factory()->create(['sort_order' => 0, 'icon' => 'heroicons:server']);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $group->id, 'locale' => 'en', 'name' => 'Backend']);
        SkillModel::factory()->create([
            'skill_group_id' => $group->id,
            'name' => 'Laravel',
            'icon' => 'devicon:laravel',
            'proficiency' => 5,
            'sort_order' => 0,
        ]);

        $contact = ContactModel::factory()->create([
            'value' => 'octocat',
            'icon' => 'simple-icons:github',
            'url' => 'https://github.com/alex',
            'sort_order' => 0,
        ]);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'GitHub']);
        ContactTranslationModel::factory()->forLocale('ru')->create(['contact_id' => $contact->id, 'name' => 'Гитхаб']);

        $this->seedFeaturedProjects(2);
    }

    /**
     * Creates published projects; the first `$featured` are also featured.
     */
    protected function seedFeaturedProjects(int $featured): void
    {
        $count = 5;
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $ids[$i] = DB::table('projects')->insertGetId([
                'slug' => "project-$i",
                'preview_image' => null,
                'preview_image_alt' => null,
                'github_url' => null,
                'live_demo_url' => null,
                'tech_writeup_article_id' => null,
                'is_featured' => $i <= $featured,
                'featured_sort_order' => $i <= $featured ? $count - $i : null,
                'is_published' => true,
                'published_at' => now(),
                'tech_tags_count' => 0,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($ids as $i => $projectId) {
            $this->translateProject($projectId, 'en', "Project $i");
        }

        DB::table('projects')->where('id', $ids[$count])->update(['deleted_at' => now()]);
    }

    protected function createProject(string $slug, array $attributes = []): int
    {
        return DB::table('projects')->insertGetId(array_merge([
            'slug' => $slug,
            'preview_image' => null,
            'preview_image_alt' => null,
            'github_url' => null,
            'live_demo_url' => null,
            'tech_writeup_article_id' => null,
            'is_featured' => false,
            'featured_sort_order' => null,
            'is_published' => true,
            'published_at' => now(),
            'tech_tags_count' => 0,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    protected function translateProject(int $projectId, string $locale, string $title): void
    {
        DB::table('project_translations')->insert([
            'project_id' => $projectId,
            'locale' => $locale,
            'title' => $title,
            'brief_description' => "Brief of $title",
            'full_description' => null,
            'problem' => null,
            'solution' => null,
            'search_vector' => null,
        ]);
    }
}
