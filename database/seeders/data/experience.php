<?php

declare(strict_types=1);

return [
    [
        'started_at' => '2024-09-01',
        'ended_at' => null,
        'sort_order' => 0,
        'project' => 'portfolio-platform',
        'external_project_name' => null,
        'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker', 'Vue', 'Ansible'],
        'en' => [
            'position' => 'Senior Backend Engineer',
            'company_name' => 'Acme GmbH',
            'company_country' => 'Germany',
            'short_description' => 'Owned the platform team: architecture, reviews and the delivery pipeline.',
            'achievements' => [
                ['description' => 'Cut p95 response times by 60% with cursor pagination and caching.', 'project_link' => null],
                ['description' => 'Introduced encrypted nightly backups with a tested restore drill.', 'project_link' => null],
                ['description' => 'Mentored two juniors to mid-level within a year.', 'project_link' => null],
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
        'started_at' => '2022-04-01',
        'ended_at' => '2024-08-31',
        'sort_order' => 1,
        'project' => null,
        'external_project_name' => null,
        'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'RabbitMQ', 'Grafana'],
        'en' => [
            'position' => 'Backend Team Lead',
            'company_name' => 'Northwind Systems',
            'company_country' => 'Poland',
            'short_description' => 'Led the backend guild and the migration off a monolithic deploy.',
            'achievements' => [
                ['description' => 'Split a monolith deploy into independently releasable services.', 'project_link' => null],
                ['description' => 'Introduced contract tests that removed most cross-team regressions.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Тимлид бэкенда',
            'company_name' => 'Northwind Systems',
            'company_country' => 'Польша',
            'short_description' => 'Руководил бэкенд-гильдией и переходом от монолитного деплоя.',
            'achievements' => [
                ['description' => 'Разделил монолитный деплой на независимо релизуемые сервисы.', 'project_link' => null],
                ['description' => 'Внедрил контрактные тесты, убравшие большинство межкомандных регрессий.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2020-01-15',
        'ended_at' => '2022-03-31',
        'sort_order' => 2,
        'project' => 'realtime-analytics-dashboard',
        'external_project_name' => null,
        'skills' => ['Vue', 'TypeScript', 'Node.js', 'Redis'],
        'en' => [
            'position' => 'Full-Stack Developer',
            'company_name' => 'Beta Labs',
            'company_country' => 'Netherlands',
            'short_description' => 'Built the customer dashboard and the public marketing site.',
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
        'started_at' => '2018-06-01',
        'ended_at' => '2019-12-31',
        'sort_order' => 3,
        'project' => null,
        'external_project_name' => 'Orion Data Platform',
        'skills' => ['PHP', 'MySQL', 'RabbitMQ', 'Docker'],
        'en' => [
            'position' => 'Software Engineer',
            'company_name' => 'Orion Data',
            'company_country' => 'Czechia',
            'short_description' => 'Worked on the ingestion platform that fed the reporting stack.',
            'achievements' => [
                ['description' => 'Reworked the ingestion queue to survive partner outages.', 'project_link' => null],
                ['description' => 'Reduced nightly batch time from six hours to under one.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Инженер-программист',
            'company_name' => 'Orion Data',
            'company_country' => 'Чехия',
            'short_description' => 'Работал над платформой приёма данных для отчётного стека.',
            'achievements' => [
                ['description' => 'Переработал очередь приёма, чтобы переживать сбои партнёров.', 'project_link' => null],
                ['description' => 'Сократил ночной батч с шести часов до менее чем одного.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2017-02-01',
        'ended_at' => '2018-05-31',
        'sort_order' => 4,
        'project' => 'inventory-api-service',
        'external_project_name' => null,
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Redis'],
        'en' => [
            'position' => 'PHP Developer',
            'company_name' => 'Gamma Soft',
            'company_country' => 'Ukraine',
            'short_description' => 'Maintained the legacy CRM and built its first REST API.',
            'achievements' => [
                ['description' => 'Automated the invoice flow and removed a week of manual work per month.', 'project_link' => null],
                ['description' => 'Introduced the team’s first automated test suite.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'PHP-разработчик',
            'company_name' => 'Gamma Soft',
            'company_country' => 'Украина',
            'short_description' => 'Поддерживал легаси-CRM и создал её первый REST API.',
            'achievements' => [
                ['description' => 'Автоматизировал поток счетов и убрал неделю ручной работы в месяц.', 'project_link' => null],
                ['description' => 'Внедрил первый в команде набор автотестов.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2016-01-01',
        'ended_at' => '2017-01-31',
        'sort_order' => 5,
        'project' => null,
        'external_project_name' => 'Delta Commerce',
        'skills' => ['PHP', 'Yii', 'MySQL', 'JavaScript'],
        'en' => [
            'position' => 'Web Developer',
            'company_name' => 'Delta Web Studio',
            'company_country' => 'Belarus',
            'short_description' => 'Delivered e-commerce features across a portfolio of client sites.',
            'achievements' => [
                ['description' => 'Built a reusable checkout module adopted by four client projects.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Веб-разработчик',
            'company_name' => 'Delta Web Studio',
            'company_country' => 'Беларусь',
            'short_description' => 'Разрабатывал функции интернет-магазинов для портфеля клиентских сайтов.',
            'achievements' => [
                ['description' => 'Создал переиспользуемый модуль оформления заказа, принятый в четырёх проектах.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2015-01-01',
        'ended_at' => '2015-12-31',
        'sort_order' => 6,
        'project' => null,
        'external_project_name' => null,
        'skills' => ['PHP', 'MySQL', 'JavaScript', 'CSS'],
        'en' => [
            'position' => 'Junior Developer',
            'company_name' => 'Epsilon Digital',
            'company_country' => 'Russia',
            'short_description' => 'Started on internal tools and quickly moved to client work.',
            'achievements' => [
                ['description' => 'Rebuilt the internal timesheet tool used by the whole studio.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Младший разработчик',
            'company_name' => 'Epsilon Digital',
            'company_country' => 'Россия',
            'short_description' => 'Начал с внутренних инструментов и быстро перешёл к клиентским задачам.',
            'achievements' => [
                ['description' => 'Переписал внутренний табель учёта времени, которым пользовалась вся студия.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2014-06-01',
        'ended_at' => '2014-12-31',
        'sort_order' => 7,
        'project' => null,
        'external_project_name' => null,
        'skills' => ['PHP', 'HTML', 'CSS', 'JavaScript'],
        'en' => [
            'position' => 'Intern Developer',
            'company_name' => 'Zeta Labs',
            'company_country' => 'Russia',
            'short_description' => 'First professional role, focused on HTML templates and small scripts.',
            'achievements' => [
                ['description' => 'Converted a set of static mockups into responsive templates.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Стажёр-разработчик',
            'company_name' => 'Zeta Labs',
            'company_country' => 'Россия',
            'short_description' => 'Первая профессиональная роль: HTML-шаблоны и небольшие скрипты.',
            'achievements' => [
                ['description' => 'Перевёл набор статичных макетов в адаптивные шаблоны.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2013-09-01',
        'ended_at' => '2014-05-31',
        'sort_order' => 8,
        'project' => null,
        'external_project_name' => null,
        'skills' => ['PHP', 'MySQL', 'HTML'],
        'en' => [
            'position' => 'Freelance Developer',
            'company_name' => 'Self-employed',
            'company_country' => 'Germany',
            'short_description' => 'Built small websites while finishing a computer science degree.',
            'achievements' => [
                ['description' => 'Delivered a dozen small business sites with custom admin panels.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Фриланс-разработчик',
            'company_name' => 'Самозанятый',
            'company_country' => 'Германия',
            'short_description' => 'Делал небольшие сайты, параллельно заканчивая университет по информатике.',
            'achievements' => [
                ['description' => 'Сдал десяток сайтов малого бизнеса с собственными админками.', 'project_link' => null],
            ],
        ],
    ],
    [
        'started_at' => '2012-10-01',
        'ended_at' => '2013-08-31',
        'sort_order' => 9,
        'project' => null,
        'external_project_name' => null,
        'skills' => ['PHP', 'HTML', 'CSS'],
        'en' => [
            'position' => 'Junior Web Developer',
            'company_name' => 'Theta Media',
            'company_country' => 'Russia',
            'short_description' => 'First industry job, maintaining small content sites.',
            'achievements' => [
                ['description' => 'Kept a fleet of content sites updated and accessible.', 'project_link' => null],
            ],
        ],
        'ru' => [
            'position' => 'Младший веб-разработчик',
            'company_name' => 'Theta Media',
            'company_country' => 'Россия',
            'short_description' => 'Первая работа в отрасли: поддержка небольших контентных сайтов.',
            'achievements' => [
                ['description' => 'Поддерживал парк контентных сайтов в актуальном и доступном состоянии.', 'project_link' => null],
            ],
        ],
    ],
];
