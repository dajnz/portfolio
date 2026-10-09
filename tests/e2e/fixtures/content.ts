/**
 * The canonical seeded demo content the e2e suites assert on. Values
 * mirror `database/seeders/E2EDemoContentSeeder.php` — keep both files
 * in sync when the dataset changes.
 */

export const demoAbout = {
    nameEn: 'Alex Kovalev',
    nameRu: 'Алекс Ковалев',
    titleEn: 'Full-Stack Developer',
} as const;

export const demoArticles = {
    queues: {
        slug: 'understanding-laravel-queues',
        titleEn: 'Understanding Laravel Queues',
        titleRu: 'Очереди Laravel: разбор',
        year: '2026',
        keyword: 'turbocharge',
    },
    vue: {
        slug: 'vue-performance-tuning',
        titleEn: 'Vue Performance Tuning',
        titleRu: 'Оптимизация производительности Vue',
        year: '2026',
    },
    postgres: {
        slug: 'postgres-full-text-search',
        titleEn: 'Full-Text Search in Postgres',
        titleRu: 'Полнотекстовый поиск в PostgreSQL',
        year: '2025',
    },
} as const;

/** The draft + trashed rows exist only for the admin list filters. */
export const demoDraftArticleSlug = 'e2e-draft-article';

export const demoArticleTags = {
    php: 'php',
    webPerformance: 'web-performance',
} as const;

export const demoSkills = ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Vue.js', 'TypeScript', 'Docker'] as const;

export const demoProjects = {
    portfolio: {
        slug: 'portfolio-platform',
        titleEn: 'Portfolio Platform',
        titleRu: 'Портфолио-платформа',
        github: 'https://github.com/e2e-dev/portfolio-platform',
    },
    realtime: {
        slug: 'realtime-analytics-dashboard',
        titleEn: 'Realtime Analytics Dashboard',
        titleRu: 'Дашборд аналитики в реальном времени',
    },
    inventory: {
        slug: 'inventory-api-service',
        titleEn: 'Inventory API Service',
        titleRu: 'API-сервис складского учёта',
    },
} as const;

export const demoDraftProjectSlug = 'e2e-draft-project';

export const demoExperience = {
    current: {
        positionEn: 'Senior Backend Engineer',
        positionRu: 'Старший бэкенд-инженер',
        companyEn: 'Acme GmbH',
        achievementEn: 'Cut p95 response times by 60% with cursor pagination and caching.',
    },
    middle: {
        positionEn: 'Full-Stack Developer',
        companyEn: 'Beta Labs',
    },
    oldest: {
        positionEn: 'PHP Developer',
        companyEn: 'Gamma Soft',
    },
} as const;
