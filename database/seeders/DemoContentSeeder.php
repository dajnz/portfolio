<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectDetailItemTranslationModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\Concerns\ProvisionsDemoImages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The rich bilingual demo dataset for the dev database: six skill
 * groups with twenty-nine skills, nine article tags, fifteen published
 * articles (the five newest cross-linked, plus a draft and a trashed
 * row), ten published projects (three featured, with engineering detail
 * items and screenshots), ten experience timeline items (one current)
 * and five contacts. Every content row is translated into `en` and
 * `ru`, and every referenced image is uploaded to RustFS.
 *
 * This is the `DatabaseSeeder` content source for local development and
 * manual testing. The lean, deterministic dataset the Playwright suites
 * assert on lives in {@see E2EDemoContentSeeder}. The seeder refuses to
 * run in production so a stray `db:seed --force` cannot inject demo
 * content (or its images) into a live database.
 */
final class DemoContentSeeder extends Seeder
{
    use ProvisionsDemoImages;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoContentSeeder must never run in production.');
        }

        $this->provisionImages();

        $skillIds = $this->seedSkills();
        $tagIds = $this->seedArticleTags();
        $articleIds = $this->seedArticles($tagIds, $skillIds);
        $projectIds = $this->seedProjects($skillIds);

        $this->seedContacts();
        $this->seedExperience($skillIds, $projectIds);
        $this->seedRelatedArticles($articleIds);
    }

    private function provisionImages(): void
    {
        $keys = [];

        foreach ($this->data('articles') as $article) {
            if ($article['lead_image'] !== null) {
                $keys[] = $article['lead_image'];
            }
        }

        foreach ($this->data('projects') as $project) {
            if ($project['preview_image'] !== null) {
                $keys[] = $project['preview_image'];
            }

            foreach ($project['screenshots'] ?? [] as $screenshot) {
                $keys[] = $screenshot['image'];
            }
        }

        foreach (array_unique($keys) as $key) {
            $this->provisionDemoImage($key, $key, 'image/svg+xml');
        }
    }

    /**
     * @return array<string, int> skill name → id
     */
    private function seedSkills(): array
    {
        $data = $this->data('skills');
        $groupIds = [];

        foreach ($data['groups'] as $sortOrder => $group) {
            $model = SkillGroupModel::query()->create([
                'sort_order' => $sortOrder,
                'icon' => $group['icon'],
            ]);

            foreach (['en', 'ru'] as $locale) {
                SkillGroupTranslationModel::query()->create([
                    'skill_group_id' => $model->id,
                    'locale' => $locale,
                    'name' => $group[$locale],
                ]);
            }

            $groupIds[$group['key']] = (int) $model->id;
        }

        $skillIds = [];

        foreach ($data['skills'] as $skill) {
            $model = SkillModel::query()->create([
                'skill_group_id' => $groupIds[$skill['group']],
                'name' => $skill['name'],
                'icon' => $skill['icon'],
                'proficiency' => $skill['proficiency'],
                'include_in_resume' => $skill['resume'],
                'sort_order' => $skill['order'],
            ]);

            $skillIds[$skill['name']] = (int) $model->id;
        }

        return $skillIds;
    }

    /**
     * @return array<string, int> tag slug → id
     */
    private function seedArticleTags(): array
    {
        $tagIds = [];

        foreach ($this->data('article_tags') as $tag) {
            $model = ArticleTagModel::query()->create(['slug' => $tag['slug']]);

            foreach (['en', 'ru'] as $locale) {
                ArticleTagTranslationModel::query()->create([
                    'article_tag_id' => $model->id,
                    'locale' => $locale,
                    'name' => $tag[$locale],
                ]);
            }

            $tagIds[$tag['slug']] = (int) $model->id;
        }

        return $tagIds;
    }

    /**
     * @param  array<string, int>  $tagIds
     * @param  array<string, int>  $skillIds
     * @return array<string, int> article slug → id
     */
    private function seedArticles(array $tagIds, array $skillIds): array
    {
        $articleIds = [];

        foreach ($this->data('articles') as $article) {
            $articleIds[$article['slug']] = $this->createArticle($article, $tagIds, $skillIds);
        }

        $draft = ArticleModel::query()->create([
            'slug' => 'demo-draft-article',
            'is_published' => false,
            'published_at' => null,
        ]);

        foreach (['en', 'ru'] as $locale) {
            ArticleTranslationModel::query()->create([
                'article_id' => $draft->id,
                'locale' => $locale,
                'title' => $locale === 'en' ? 'Draft article never published' : 'Черновик статьи',
                'short_description' => $locale === 'en'
                    ? 'A draft row for the admin list filters.'
                    : 'Черновик для фильтров админского списка.',
                'content' => "## Unfinished\n\nThis article is never published and must stay off the public blog.",
            ]);
        }

        $trashed = ArticleModel::query()->create([
            'slug' => 'demo-trashed-article',
            'is_published' => false,
            'published_at' => null,
        ]);

        $trashed->delete();

        return $articleIds;
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  array<string, int>  $tagIds
     * @param  array<string, int>  $skillIds
     */
    private function createArticle(array $article, array $tagIds, array $skillIds): int
    {
        $leadImage = $article['lead_image'];

        $model = ArticleModel::query()->create([
            'slug' => $article['slug'],
            'lead_image' => $leadImage,
            'lead_image_alt' => $leadImage === null ? null : [
                'en' => $article['en']['lead_image_alt'],
                'ru' => $article['ru']['lead_image_alt'],
            ],
            'is_published' => true,
            'published_at' => $article['published_at'],
        ]);

        foreach (['en', 'ru'] as $locale) {
            ArticleTranslationModel::query()->create([
                'article_id' => $model->id,
                'locale' => $locale,
                'title' => $article[$locale]['title'],
                'short_description' => $article[$locale]['short_description'],
                'content' => $this->articleContent($locale, $article['slug']),
            ]);
        }

        DB::table('article_tag_pivot')->insert([
            'article_id' => $model->id,
            'tag_id' => $tagIds[$article['tag']],
        ]);

        foreach ($article['skills'] as $skill) {
            DB::table('article_skill_pivot')->insert([
                'article_id' => $model->id,
                'skill_id' => $skillIds[$skill],
            ]);
        }

        return (int) $model->id;
    }

    /**
     * @param  array<string, int>  $skillIds
     * @return array<string, int> project slug → id
     */
    private function seedProjects(array $skillIds): array
    {
        $projectIds = [];

        foreach ($this->data('projects') as $project) {
            $projectIds[$project['slug']] = $this->createProject($project, $skillIds);
        }

        $draft = ProjectModel::query()->create([
            'slug' => 'demo-draft-project',
            'is_featured' => false,
            'featured_sort_order' => null,
            'is_published' => false,
            'published_at' => null,
            'tech_tags_count' => 0,
        ]);

        foreach (['en', 'ru'] as $locale) {
            ProjectTranslationModel::query()->create([
                'project_id' => $draft->id,
                'locale' => $locale,
                'title' => $locale === 'en' ? 'Draft project never published' : 'Черновик проекта',
                'brief_description' => $locale === 'en'
                    ? 'A draft row for the admin list filters.'
                    : 'Черновик для фильтров админского списка.',
            ]);
        }

        return $projectIds;
    }

    /**
     * @param  array<string, mixed>  $project
     * @param  array<string, int>  $skillIds
     */
    private function createProject(array $project, array $skillIds): int
    {
        $previewImage = $project['preview_image'];

        $model = ProjectModel::query()->create([
            'slug' => $project['slug'],
            'preview_image' => $previewImage,
            'preview_image_alt' => $previewImage === null ? null : [
                'en' => $project['en']['title'],
                'ru' => $project['ru']['title'],
            ],
            'github_url' => $project['github_url'],
            'live_demo_url' => $project['live_demo_url'],
            'tech_writeup_article_id' => null,
            'is_featured' => $project['featured_order'] !== null,
            'featured_sort_order' => $project['featured_order'],
            'is_published' => true,
            'published_at' => $project['published_at'],
            'tech_tags_count' => count($project['skills']),
        ]);

        foreach (['en', 'ru'] as $locale) {
            ProjectTranslationModel::query()->create([
                'project_id' => $model->id,
                'locale' => $locale,
                'title' => $project[$locale]['title'],
                'brief_description' => $project[$locale]['brief_description'],
                'full_description' => $project[$locale]['full_description'],
                'problem' => $project[$locale]['problem'],
                'solution' => $project[$locale]['solution'],
            ]);
        }

        foreach ($project['skills'] as $skill) {
            DB::table('project_tech_tags')->insert([
                'project_id' => $model->id,
                'skill_id' => $skillIds[$skill],
            ]);
        }

        $this->seedProjectDetailItems((int) $model->id, $project['detail_items'] ?? []);
        $this->seedProjectScreenshots((int) $model->id, $project['screenshots'] ?? []);

        return (int) $model->id;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function seedProjectDetailItems(int $projectId, array $items): void
    {
        foreach ($items as $sortOrder => $item) {
            $model = ProjectDetailItemModel::query()->create([
                'project_id' => $projectId,
                'icon' => $item['icon'],
                'sort_order' => $sortOrder,
            ]);

            foreach (['en', 'ru'] as $locale) {
                ProjectDetailItemTranslationModel::query()->create([
                    'detail_item_id' => $model->id,
                    'locale' => $locale,
                    'title' => $item[$locale]['title'],
                    'description' => $item[$locale]['description'],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $screenshots
     */
    private function seedProjectScreenshots(int $projectId, array $screenshots): void
    {
        foreach ($screenshots as $screenshot) {
            ProjectScreenshotModel::query()->create([
                'project_id' => $projectId,
                'image_path' => $screenshot['image'],
                'alt_text' => [
                    'en' => $screenshot['en']['alt'],
                    'ru' => $screenshot['ru']['alt'],
                ],
                'title' => [
                    'en' => $screenshot['en']['title'],
                    'ru' => $screenshot['ru']['title'],
                ],
                'description' => [
                    'en' => $screenshot['en']['description'],
                    'ru' => $screenshot['ru']['description'],
                ],
                'sort_order' => $screenshot['order'],
            ]);
        }
    }

    private function seedContacts(): void
    {
        foreach ($this->data('contacts') as $contact) {
            $model = ContactModel::query()->create([
                'value' => $contact['value'],
                'icon' => $contact['icon'],
                'url' => $contact['url'],
                'sort_order' => $contact['order'],
                'include_in_resume' => $contact['resume'],
            ]);

            foreach (['en', 'ru'] as $locale) {
                ContactTranslationModel::query()->create([
                    'contact_id' => $model->id,
                    'locale' => $locale,
                    'name' => $contact[$locale],
                ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $skillIds
     * @param  array<string, int>  $projectIds
     */
    private function seedExperience(array $skillIds, array $projectIds): void
    {
        foreach ($this->data('experience') as $item) {
            $model = TimelineItemModel::query()->create([
                'started_at' => $item['started_at'],
                'ended_at' => $item['ended_at'],
                'is_published' => true,
                'sort_order' => $item['sort_order'],
                'project_id' => $item['project'] === null ? null : $projectIds[$item['project']],
                'include_in_resume' => true,
                'external_project_name' => $item['external_project_name'],
            ]);

            foreach (['en', 'ru'] as $locale) {
                TimelineItemTranslationModel::query()->create([
                    'item_id' => $model->id,
                    'locale' => $locale,
                    'position' => $item[$locale]['position'],
                    'company_name' => $item[$locale]['company_name'],
                    'company_country' => $item[$locale]['company_country'],
                    'short_description' => $item[$locale]['short_description'],
                    'achievements' => $item[$locale]['achievements'],
                ]);
            }

            foreach ($item['skills'] as $skill) {
                DB::table('experience_item_tech_tags')->insert([
                    'experience_item_id' => $model->id,
                    'skill_id' => $skillIds[$skill],
                ]);
            }
        }
    }

    /**
     * Links each of the five newest published articles to two related
     * posts, so the "related articles" block is populated on the public
     * blog detail page.
     *
     * @param  array<string, int>  $articleIds
     */
    private function seedRelatedArticles(array $articleIds): void
    {
        $links = [
            'designing-bilingual-content-models' => ['postgres-full-text-search-at-scale', 'caching-layers-explained'],
            'postgres-full-text-search-at-scale' => ['designing-bilingual-content-models', 'redis-patterns-for-read-heavy-apps'],
            'keeping-a-vue-performance-budget' => ['composable-vue-architecture', 'caching-layers-explained'],
            'queue-reliability-patterns' => ['zero-downtime-database-migrations', 'structured-logging-and-observability'],
            'testing-domain-logic-without-the-framework' => ['zero-downtime-database-migrations', 'composable-vue-architecture'],
        ];

        foreach ($links as $slug => $relatedSlugs) {
            foreach ($relatedSlugs as $relatedSlug) {
                DB::table('related_articles')->insert([
                    'article_id' => $articleIds[$slug],
                    'related_article_id' => $articleIds[$relatedSlug],
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function data(string $name): array
    {
        return require database_path('seeders/data/'.$name.'.php');
    }

    private function articleContent(string $locale, string $slug): string
    {
        return (string) file_get_contents(
            database_path("seeders/data/articles/{$locale}/{$slug}.md"),
        );
    }
}
