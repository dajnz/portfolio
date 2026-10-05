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
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The lean, deterministic e2e fixture dataset (testing.md:553 — the
 * E2ETestSeeder delegate; added in step 13.2). The richer dev dataset
 * lives in {@see DemoContentSeeder}; this one keeps the exact row
 * counts, order and slugs the Playwright suites assert on: three skill
 * groups with seven skills, two article tags, three published blog
 * articles spread over two years (plus a draft and a trashed row),
 * three published projects (two featured, one with engineering detail
 * items), three experience items (one current) and three contacts.
 * Every content row is fully translated into `en` and `ru`, so the
 * public pages render both locales.
 *
 * The large content literals live in the `ARTICLES`, `PROJECTS` and
 * `EXPERIENCE_ITEMS` constants so the seed methods stay small and
 * readable. No images are seeded — the public surfaces render their
 * placeholders, keeping the dataset free of RustFS dependencies. Row
 * creation goes through the Eloquent models so the model hooks
 * (reading time, FTS `search_vector`, sitemap invalidation) behave
 * exactly as in the app.
 */
final class E2EDemoContentSeeder extends Seeder
{
    /**
     * The fully translated blog article rows (three published). The
     * draft and trashed rows are created by the seed method itself.
     *
     * @var list<array<string, mixed>>
     */
    private const ARTICLES = [
        [
            'slug' => 'understanding-laravel-queues',
            'published_at' => '2026-05-12 09:00:00',
            'tag' => 'php',
            'skills' => ['Laravel', 'Redis'],
            'en' => [
                'title' => 'Understanding Laravel Queues',
                'short_description' => 'A practical deep dive into queue workers, retries and backoff.',
                'content' => <<<'MD'
                    Queues let you turbocharge slow requests by moving work elsewhere. Long-running jobs do not belong in a web request: sending email, resizing images or calling third-party APIs should happen later, in the background.

                    ## Why queues matter

                    A worker process pulls jobs off the queue and runs them one by one. When a job fails, Laravel releases it back onto the queue with a delay instead of losing it.

                    ```php
                    php artisan queue:work
                    ```

                    > [!TIP]
                    > Store failed jobs in a dedicated table so nothing is lost silently.

                    ## Retries and backoff

                    Every job should tolerate failure. Set a sensible retry limit and let the framework back off between attempts.

                    ## Monitoring queues

                    Watch the queue length and the failed-job count. A sudden spike in failed jobs almost always means a broken dependency, not a broken queue.
                    MD,
            ],
            'ru' => [
                'title' => 'Очереди Laravel: разбор',
                'short_description' => 'Практический разбор воркеров, повторов и отсрочки в очередях.',
                'content' => <<<'MD'
                    Очереди позволяют ускорить медленные запросы, перенося работу в фон. Долгие операции не должны выполняться в веб-запросе: отправка писем, сжатие изображений или вызовы сторонних API лучше отложить.

                    ## Зачем нужны очереди

                    Воркер забирает задания из очереди и выполняет их по одному. Когда задание падает, Laravel возвращает его в очередь с задержкой, а не теряет.

                    ```php
                    php artisan queue:work
                    ```

                    > [!TIP]
                    > Храните упавшие задания в отдельной таблице, чтобы ничего не терялось молча.

                    ## Повторы и отсрочка

                    Каждое задание должно переживать сбой. Задайте разумное число попыток и позвольте фреймворку выдерживать паузу между ними.

                    ## Наблюдение за очередями

                    Следите за длиной очереди и числом упавших заданий. Резкий рост числа падений почти всегда означает сломанную зависимость, а не сломанную очередь.
                    MD,
            ],
        ],
        [
            'slug' => 'vue-performance-tuning',
            'published_at' => '2026-04-20 10:00:00',
            'tag' => 'web-performance',
            'skills' => ['Vue.js', 'TypeScript'],
            'en' => [
                'title' => 'Vue Performance Tuning',
                'short_description' => 'Cutting bundle size and rendering cost in a Vue single-page app.',
                'content' => <<<'MD'
                    Hydration is the most expensive part of a client-rendered page. This article collects the tuning steps that brought our Vue bundle down to a third of its original size.

                    ## Bundle size

                    Start with the bundle report. Route-level code splitting alone removed half of the initial payload.

                    ## Rendering cost

                    Memoize expensive computed values and virtualize long lists. Plain `computed` covers most real cases.

                    ## Measurement

                    Keep a performance budget in CI. A regression that ships quietly costs far more than the minutes spent measuring it.
                    MD,
            ],
            'ru' => [
                'title' => 'Оптимизация производительности Vue',
                'short_description' => 'Уменьшаем размер бандла и стоимость рендеринга в SPA на Vue.',
                'content' => <<<'MD'
                    Гидратация — самая дорогая часть клиентского рендеринга. В статье собраны шаги, которые сократили бандл нашего Vue-приложения втрое.

                    ## Размер бандла

                    Начните с отчёта по бандлу. Одно только разделение кода по маршрутам убрало половину начальной загрузки.

                    ## Стоимость рендеринга

                    Мемоизируйте дорогие вычисления и виртуализируйте длинные списки. Обычного `computed` хватает в большинстве реальных случаев.

                    ## Измерение

                    Держите бюджет производительности в CI. Регрессия, попавшая в прод незаметно, обходится дороже минут, потраченных на замеры.
                    MD,
            ],
        ],
        [
            'slug' => 'postgres-full-text-search',
            'published_at' => '2025-10-05 08:30:00',
            'tag' => 'php',
            'skills' => ['PostgreSQL'],
            'en' => [
                'title' => 'Full-Text Search in Postgres',
                'short_description' => 'Ranking, highlighting and typo tolerance without a search server.',
                'content' => <<<'MD'
                    Postgres ships a capable full-text engine. For a small blog or a portfolio it removes the need for a dedicated search service.

                    ## Vectors and dictionaries

                    A tsvector column stores the lexemes of a document. The Snowball dictionary normalizes English and Russian the same way.

                    ## Ranking

                    Ts_rank with a normalized divisor keeps short documents from dominating the results.

                    ## Headlines

                    Ts_headline produces the highlighted snippet the search results page renders with mark tags.
                    MD,
            ],
            'ru' => [
                'title' => 'Полнотекстовый поиск в PostgreSQL',
                'short_description' => 'Ранжирование, подсветка и устойчивость к опечаткам без поискового сервера.',
                'content' => <<<'MD'
                    В PostgreSQL есть полноценный полнотекстовый движок. Для небольшого блога или портфолио он избавляет от отдельного поискового сервиса.

                    ## Векторы и словари

                    Колонка tsvector хранит лексемы документа. Словарь Snowball одинаково нормализует английский и русский текст.

                    ## Ранжирование

                    Ts_rank с нормализованным делителем не даёт коротким документам доминировать в выдаче.

                    ## Заголовки

                    Ts_headline строит подсвеченный фрагмент, который страница результатов рендерит с тегами mark.
                    MD,
            ],
        ],
    ];

    /**
     * The fully translated project rows: three published (two featured,
     * one with engineering detail items) plus the draft row.
     *
     * @var list<array<string, mixed>>
     */
    private const PROJECTS = [
        [
            'slug' => 'portfolio-platform',
            'featured_order' => 1,
            'published_at' => '2026-06-01 12:00:00',
            'github_url' => 'https://github.com/e2e-dev/portfolio-platform',
            'live_demo_url' => null,
            'skills' => ['Laravel', 'Vue.js', 'PostgreSQL'],
            'en' => [
                'title' => 'Portfolio Platform',
                'brief_description' => 'The bilingual portfolio with a blog you are reading right now.',
                'full_description' => "## Architecture\n\nThe platform is a Laravel application serving an Inertia single-page frontend. Public pages are rendered with SSR on first load, the admin lives behind a session with TOTP.",
                'problem' => 'The author needed one deployable home for a portfolio, a blog and a resume generator.',
                'solution' => 'A single Laravel codebase with strict feature boundaries, encrypted backups and a PostgreSQL full-text search.',
            ],
            'ru' => [
                'title' => 'Портфолио-платформа',
                'brief_description' => 'Двуязычное портфолио с блогом, которое вы сейчас читаете.',
                'full_description' => "## Архитектура\n\nПлатформа — приложение на Laravel, обслуживающее SPA-фронтенд на Inertia. Публичные страницы рендерятся через SSR при первой загрузке, админка работает за сессией с TOTP.",
                'problem' => 'Автору нужен был один разворачиваемый дом для портфолио, блога и генератора резюме.',
                'solution' => 'Единая кодовая база на Laravel со строгими границами фич, шифрованными бэкапами и полнотекстовым поиском PostgreSQL.',
            ],
        ],
        [
            'slug' => 'realtime-analytics-dashboard',
            'featured_order' => 2,
            'published_at' => '2025-03-15 09:15:00',
            'github_url' => 'https://github.com/e2e-dev/realtime-analytics-dashboard',
            'live_demo_url' => null,
            'skills' => ['Vue.js', 'TypeScript', 'Redis'],
            'en' => [
                'title' => 'Realtime Analytics Dashboard',
                'brief_description' => 'A streaming dashboard that aggregates millions of daily events.',
                'full_description' => "## Streaming pipeline\n\nEvents arrive over a persistent connection and are aggregated into per-minute buckets in Redis.",
                'problem' => 'Existing charts could not keep up with the event volume of the product.',
                'solution' => 'A WebSocket pipeline with Redis-backed aggregations and a virtualized chart grid.',
            ],
            'ru' => [
                'title' => 'Дашборд аналитики в реальном времени',
                'brief_description' => 'Стриминговый дашборд, агрегирующий миллионы событий в день.',
                'full_description' => "## Стриминговый конвейер\n\nСобытия приходят по постоянному соединению и агрегируются в поминутные корзины в Redis.",
                'problem' => 'Существующие графики не справлялись с объёмом событий продукта.',
                'solution' => 'Конвейер на WebSocket с агрегацией в Redis и виртуализированной сеткой графиков.',
            ],
        ],
        [
            'slug' => 'inventory-api-service',
            'featured_order' => null,
            'published_at' => '2024-11-20 14:45:00',
            'github_url' => null,
            'live_demo_url' => null,
            'skills' => ['PHP', 'Laravel', 'PostgreSQL'],
            'en' => [
                'title' => 'Inventory API Service',
                'brief_description' => 'A headless stock-management API with idempotent writes.',
                'full_description' => "## Design\n\nThe service exposes idempotent write endpoints and streams stock changes over server-sent events.",
                'problem' => 'Warehouse clients mutated stock with conflicting concurrent writes.',
                'solution' => 'An event-sourced inventory core with optimistic concurrency and retries.',
            ],
            'ru' => [
                'title' => 'API-сервис складского учёта',
                'brief_description' => 'Бессерверный API управления остатками с идемпотентными записями.',
                'full_description' => "## Устройство\n\nСервис отдаёт идемпотентные эндпоинты записи и транслирует изменения остатков через server-sent events.",
                'problem' => 'Клиенты склада конфликтовали при параллельном изменении остатков.',
                'solution' => 'Событийное ядро учёта с оптимистичной блокировкой и повторами.',
            ],
        ],
        [
            'slug' => 'e2e-draft-project',
            'featured_order' => null,
            'published_at' => null,
            'github_url' => null,
            'live_demo_url' => null,
            'skills' => ['Laravel'],
            'en' => [
                'title' => 'Draft Project Never Published',
                'brief_description' => 'A draft project row for the admin list filters.',
                'full_description' => null,
                'problem' => null,
                'solution' => null,
            ],
            'ru' => [
                'title' => 'Черновик проекта, который не публиковался',
                'brief_description' => 'Черновик проекта для фильтров админского списка.',
                'full_description' => null,
                'problem' => null,
                'solution' => null,
            ],
        ],
    ];

    /**
     * The fully translated experience rows (newest first; the first
     * entry is the current, still-open role).
     *
     * @var list<array<string, mixed>>
     */
    private const EXPERIENCE_ITEMS = [
        [
            'started_at' => '2024-09-01',
            'ended_at' => null,
            'sort_order' => 0,
            'project_id' => 'portfolio-platform',
            'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker', 'Vue.js'],
            'en' => [
                'position' => 'Senior Backend Engineer',
                'company_name' => 'Acme GmbH',
                'company_country' => 'Germany',
                'short_description' => 'Owned the platform team: architecture, reviews and the delivery pipeline.',
                'achievements' => [
                    ['description' => 'Cut p95 response times by 60% with cursor pagination and caching.', 'project_link' => null],
                    ['description' => 'Introduced encrypted nightly backups with a tested restore drill.', 'project_link' => null],
                    ['description' => 'Mentored two juniors to mid-level in a year.', 'project_link' => null],
                ],
            ],
            'ru' => [
                'position' => 'Старший бэкенд-инженер',
                'company_name' => 'Acme GmbH',
                'company_country' => 'Германия',
                'short_description' => 'Отвечал за платформенную команду: архитектура, ревью и конвейер доставки.',
                'achievements' => [
                    ['description' => 'Сократил p95 время ответа на 60% с помощью курсорной пагинации и кэша.', 'project_link' => null],
                    ['description' => 'Внедрил шифрованные ночные бэкапы с проверенным сценарием восстановления.', 'project_link' => null],
                    ['description' => 'За год вывел двух джуниоров до мидла.', 'project_link' => null],
                ],
            ],
        ],
        [
            'started_at' => '2021-06-01',
            'ended_at' => '2024-04-30',
            'sort_order' => 1,
            'project_id' => null,
            'skills' => ['Vue.js', 'TypeScript', 'PHP'],
            'en' => [
                'position' => 'Full-Stack Developer',
                'company_name' => 'Beta Labs',
                'company_country' => 'Netherlands',
                'short_description' => 'Built the customer dashboard and the public-facing marketing site.',
                'achievements' => [
                    ['description' => 'Shipped the analytics dashboard used by 40k monthly users.', 'project_link' => null],
                    ['description' => 'Moved the frontend to TypeScript and cut the bundle by half.', 'project_link' => null],
                ],
            ],
            'ru' => [
                'position' => 'Фулстек-разработчик',
                'company_name' => 'Beta Labs',
                'company_country' => 'Нидерланды',
                'short_description' => 'Разрабатывал клиентский дашборд и публичный маркетинговый сайт.',
                'achievements' => [
                    ['description' => 'Запустил дашборд аналитики для 40 тысяч пользователей в месяц.', 'project_link' => null],
                    ['description' => 'Перевёл фронтенд на TypeScript и вдвое уменьшил бандл.', 'project_link' => null],
                ],
            ],
        ],
        [
            'started_at' => '2019-01-15',
            'ended_at' => '2021-05-31',
            'sort_order' => 2,
            'project_id' => null,
            'skills' => ['PHP'],
            'en' => [
                'position' => 'PHP Developer',
                'company_name' => 'Gamma Soft',
                'company_country' => 'Ukraine',
                'short_description' => 'Maintained the legacy CRM and its integration layer.',
                'achievements' => [
                    ['description' => 'Automated the invoice flow and removed a week of manual work per month.', 'project_link' => null],
                ],
            ],
            'ru' => [
                'position' => 'PHP-разработчик',
                'company_name' => 'Gamma Soft',
                'company_country' => 'Украина',
                'short_description' => 'Поддерживал легаси-CRM и слой интеграций.',
                'achievements' => [
                    ['description' => 'Автоматизировал поток счетов и убрал неделю ручной работы в месяц.', 'project_link' => null],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $skillIds = $this->seedSkills();
        $tagIds = $this->seedArticleTags();
        $articleIds = $this->seedBlogArticles($tagIds, $skillIds);
        $projectIds = $this->seedProjects($skillIds);

        $this->seedContacts();
        $this->seedExperience($skillIds, $projectIds);
        $this->seedRelatedArticles($articleIds);
    }

    /**
     * @return array<string, int> skill name → id
     */
    private function seedSkills(): array
    {
        $groups = [
            'backend' => ['icon' => 'heroicons:cpu-chip', 'en' => 'Backend', 'ru' => 'Бэкенд'],
            'frontend' => ['icon' => 'heroicons:device-phone-mobile', 'en' => 'Frontend', 'ru' => 'Фронтенд'],
            'devops' => ['icon' => 'heroicons:cog-6-tooth', 'en' => 'DevOps', 'ru' => 'Девопс'],
        ];

        $skills = [
            ['name' => 'PHP', 'icon' => 'devicon:php', 'proficiency' => 5, 'group' => 'backend', 'order' => 0],
            ['name' => 'Laravel', 'icon' => 'devicon:laravel', 'proficiency' => 5, 'group' => 'backend', 'order' => 1],
            ['name' => 'PostgreSQL', 'icon' => 'devicon:postgresql', 'proficiency' => 4, 'group' => 'backend', 'order' => 2],
            ['name' => 'Redis', 'icon' => 'devicon:redis', 'proficiency' => 4, 'group' => 'backend', 'order' => 3],
            ['name' => 'Vue.js', 'icon' => 'devicon:vuejs', 'proficiency' => 5, 'group' => 'frontend', 'order' => 0],
            ['name' => 'TypeScript', 'icon' => 'devicon:typescript', 'proficiency' => 4, 'group' => 'frontend', 'order' => 1],
            ['name' => 'Docker', 'icon' => 'devicon:docker', 'proficiency' => 3, 'group' => 'devops', 'order' => 0],
        ];

        $skillIds = [];

        foreach ($groups as $groupKey => $group) {
            $model = SkillGroupModel::query()->create([
                'sort_order' => array_search($groupKey, array_keys($groups), true),
                'icon' => $group['icon'],
            ]);

            foreach (['en' => $group['en'], 'ru' => $group['ru']] as $locale => $name) {
                SkillGroupTranslationModel::query()->create([
                    'skill_group_id' => $model->id,
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }

            foreach ($skills as $skill) {
                if ($skill['group'] !== $groupKey) {
                    continue;
                }

                $row = SkillModel::query()->create([
                    'skill_group_id' => $model->id,
                    'name' => $skill['name'],
                    'icon' => $skill['icon'],
                    'proficiency' => $skill['proficiency'],
                    'include_in_resume' => true,
                    'sort_order' => $skill['order'],
                ]);

                $skillIds[$skill['name']] = (int) $row->id;
            }
        }

        return $skillIds;
    }

    /**
     * @return array<string, int> tag slug → id
     */
    private function seedArticleTags(): array
    {
        $tags = [
            'php' => ['en' => 'PHP', 'ru' => 'PHP'],
            'web-performance' => ['en' => 'Web Performance', 'ru' => 'Веб-производительность'],
        ];

        $tagIds = [];

        foreach ($tags as $slug => $names) {
            $tag = ArticleTagModel::query()->create(['slug' => $slug]);

            foreach ($names as $locale => $name) {
                ArticleTagTranslationModel::query()->create([
                    'article_tag_id' => $tag->id,
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }

            $tagIds[$slug] = (int) $tag->id;
        }

        return $tagIds;
    }

    /**
     * @param  array<string, int>  $tagIds
     * @param  array<string, int>  $skillIds
     * @return array<string, int> article slug → id
     */
    private function seedBlogArticles(array $tagIds, array $skillIds): array
    {
        $articleIds = [];

        foreach (self::ARTICLES as $article) {
            $model = ArticleModel::query()->create([
                'slug' => $article['slug'],
                'is_published' => true,
                'published_at' => $article['published_at'],
                'is_featured' => false,
            ]);

            foreach (['en', 'ru'] as $locale) {
                ArticleTranslationModel::query()->create([
                    'article_id' => $model->id,
                    'locale' => $locale,
                    'title' => $article[$locale]['title'],
                    'short_description' => $article[$locale]['short_description'],
                    'content' => $article[$locale]['content'],
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

            $articleIds[$article['slug']] = (int) $model->id;
        }

        $draft = ArticleModel::query()->create([
            'slug' => 'e2e-draft-article',
            'is_published' => false,
            'published_at' => null,
            'is_featured' => false,
        ]);

        ArticleTranslationModel::query()->create([
            'article_id' => $draft->id,
            'locale' => 'en',
            'title' => 'Draft article never published',
            'short_description' => 'A draft row for the admin list filters.',
            'content' => "## Unfinished\n\nThis article is never published and must stay off the public blog.",
        ]);

        $articleIds['e2e-draft-article'] = (int) $draft->id;

        $trashed = ArticleModel::query()->create([
            'slug' => 'e2e-trashed-article',
            'is_published' => false,
            'published_at' => null,
            'is_featured' => false,
        ]);

        $trashed->delete();

        return $articleIds;
    }

    /**
     * @param  array<string, int>  $skillIds
     * @return array<string, int> project slug → id
     */
    private function seedProjects(array $skillIds): array
    {
        $projectIds = [];

        foreach (self::PROJECTS as $project) {
            $model = ProjectModel::query()->create([
                'slug' => $project['slug'],
                'preview_image' => null,
                'preview_image_alt' => null,
                'github_url' => $project['github_url'],
                'live_demo_url' => $project['live_demo_url'],
                'tech_writeup_article_id' => null,
                'is_featured' => $project['featured_order'] !== null,
                'featured_sort_order' => $project['featured_order'],
                'is_published' => $project['published_at'] !== null,
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

            if ($project['slug'] === 'portfolio-platform') {
                $this->seedEngineeringDetailItems($model->id);
            }

            $projectIds[$project['slug']] = (int) $model->id;
        }

        return $projectIds;
    }

    private function seedEngineeringDetailItems(int $projectId): void
    {
        $details = [
            ['sort_order' => 0, 'en' => ['title' => 'Accessibility', 'description' => 'WCAG 2.2 AA on the public pages, audited with axe in CI.'], 'ru' => ['title' => 'Доступность', 'description' => 'WCAG 2.2 AA на публичных страницах, аудит axe в CI.']],
            ['sort_order' => 1, 'en' => ['title' => 'Encrypted backups', 'description' => 'AES-256 archives with an offline key, restorable from anywhere.'], 'ru' => ['title' => 'Шифрованные бэкапы', 'description' => 'Архивы AES-256 с офлайн-ключом, восстанавливаемые откуда угодно.']],
        ];

        foreach ($details as $detail) {
            $item = ProjectDetailItemModel::query()->create([
                'project_id' => $projectId,
                'icon' => null,
                'sort_order' => $detail['sort_order'],
            ]);

            foreach (['en', 'ru'] as $locale) {
                ProjectDetailItemTranslationModel::query()->create([
                    'detail_item_id' => $item->id,
                    'locale' => $locale,
                    'title' => $detail[$locale]['title'],
                    'description' => $detail[$locale]['description'],
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
        foreach (self::EXPERIENCE_ITEMS as $item) {
            $model = TimelineItemModel::query()->create([
                'started_at' => $item['started_at'],
                'ended_at' => $item['ended_at'],
                'is_published' => true,
                'sort_order' => $item['sort_order'],
                'project_id' => $item['project_id'] !== null ? $projectIds[$item['project_id']] : null,
                'include_in_resume' => true,
                'external_project_name' => null,
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

    private function seedContacts(): void
    {
        $contacts = [
            [
                'value' => 'e2e-dev',
                'icon' => 'simple-icons:github',
                'url' => 'https://github.com/e2e-dev',
                'sort_order' => 0,
                'names' => ['en' => 'GitHub', 'ru' => 'Гитхаб'],
            ],
            [
                'value' => 'hello@e2e.dev',
                'icon' => 'simple-icons:gmail',
                'url' => 'mailto:hello@e2e.dev',
                'sort_order' => 1,
                'names' => ['en' => 'Email', 'ru' => 'Почта'],
            ],
            [
                'value' => '@e2e_dev',
                'icon' => 'simple-icons:telegram',
                'url' => 'https://t.me/e2e_dev',
                'sort_order' => 2,
                'names' => ['en' => 'Telegram', 'ru' => 'Телеграм'],
            ],
        ];

        foreach ($contacts as $contact) {
            $model = ContactModel::query()->create([
                'value' => $contact['value'],
                'icon' => $contact['icon'],
                'url' => $contact['url'],
                'sort_order' => $contact['sort_order'],
                'include_in_resume' => true,
            ]);

            foreach ($contact['names'] as $locale => $name) {
                ContactTranslationModel::query()->create([
                    'contact_id' => $model->id,
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $articleIds
     */
    private function seedRelatedArticles(array $articleIds): void
    {
        DB::table('related_articles')->insert([
            ['article_id' => $articleIds['understanding-laravel-queues'], 'related_article_id' => $articleIds['vue-performance-tuning']],
            ['article_id' => $articleIds['understanding-laravel-queues'], 'related_article_id' => $articleIds['postgres-full-text-search']],
        ]);
    }
}
