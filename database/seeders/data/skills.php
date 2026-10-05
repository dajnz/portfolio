<?php

declare(strict_types=1);

return [
    'groups' => [
        ['key' => 'backend', 'icon' => 'heroicons:server', 'en' => 'Backend', 'ru' => 'Бэкенд'],
        ['key' => 'frontend', 'icon' => 'heroicons:device-phone-mobile', 'en' => 'Frontend', 'ru' => 'Фронтенд'],
        ['key' => 'devops', 'icon' => 'heroicons:cog-6-tooth', 'en' => 'DevOps', 'ru' => 'Девопс'],
        ['key' => 'data', 'icon' => 'heroicons:circle-stack', 'en' => 'DB and Queues', 'ru' => 'БД и очереди'],
        ['key' => 'tools', 'icon' => 'heroicons:wrench-screwdriver', 'en' => 'Tools', 'ru' => 'Инструменты'],
        ['key' => 'ai', 'icon' => 'heroicons:sparkles', 'en' => 'AI', 'ru' => 'ИИ'],
    ],

    'skills' => [
        ['name' => 'TypeScript', 'icon' => 'devicon:typescript', 'proficiency' => 5, 'group' => 'frontend', 'order' => 0, 'resume' => true],
        ['name' => 'JavaScript', 'icon' => 'devicon:javascript', 'proficiency' => 5, 'group' => 'frontend', 'order' => 1, 'resume' => true],
        ['name' => 'Vue', 'icon' => 'devicon:vuejs', 'proficiency' => 5, 'group' => 'frontend', 'order' => 2, 'resume' => true],
        ['name' => 'HTML', 'icon' => 'devicon:html5', 'proficiency' => 5, 'group' => 'frontend', 'order' => 3, 'resume' => true],
        ['name' => 'CSS', 'icon' => 'devicon:css3', 'proficiency' => 4, 'group' => 'frontend', 'order' => 4, 'resume' => true],
        ['name' => 'Jest', 'icon' => null, 'proficiency' => 3, 'group' => 'frontend', 'order' => 5, 'resume' => false],
        ['name' => 'Vitest', 'icon' => 'devicon:vitest', 'proficiency' => 4, 'group' => 'frontend', 'order' => 6, 'resume' => true],
        ['name' => 'Tailwind', 'icon' => 'devicon:tailwindcss', 'proficiency' => 5, 'group' => 'frontend', 'order' => 7, 'resume' => true],
        ['name' => 'Bootstrap', 'icon' => 'devicon:bootstrap', 'proficiency' => 3, 'group' => 'frontend', 'order' => 8, 'resume' => false],

        ['name' => 'PHP', 'icon' => 'devicon:php', 'proficiency' => 5, 'group' => 'backend', 'order' => 0, 'resume' => true],
        ['name' => 'Laravel', 'icon' => 'devicon:laravel', 'proficiency' => 5, 'group' => 'backend', 'order' => 1, 'resume' => true],
        ['name' => 'Yii', 'icon' => 'devicon:yii', 'proficiency' => 4, 'group' => 'backend', 'order' => 2, 'resume' => true],
        ['name' => 'Node.js', 'icon' => 'devicon:nodejs', 'proficiency' => 4, 'group' => 'backend', 'order' => 3, 'resume' => true],

        ['name' => 'MySQL', 'icon' => 'devicon:mysql', 'proficiency' => 4, 'group' => 'data', 'order' => 0, 'resume' => true],
        ['name' => 'PostgreSQL', 'icon' => 'devicon:postgresql', 'proficiency' => 5, 'group' => 'data', 'order' => 1, 'resume' => true],
        ['name' => 'Redis', 'icon' => 'devicon:redis', 'proficiency' => 4, 'group' => 'data', 'order' => 2, 'resume' => true],
        ['name' => 'RabbitMQ', 'icon' => 'devicon:rabbitmq', 'proficiency' => 3, 'group' => 'data', 'order' => 3, 'resume' => true],

        ['name' => 'Docker', 'icon' => 'devicon:docker', 'proficiency' => 4, 'group' => 'devops', 'order' => 0, 'resume' => true],
        ['name' => 'Ansible', 'icon' => 'devicon:ansible', 'proficiency' => 4, 'group' => 'devops', 'order' => 1, 'resume' => true],
        ['name' => 'Sentry', 'icon' => 'devicon:sentry', 'proficiency' => 3, 'group' => 'devops', 'order' => 2, 'resume' => false],
        ['name' => 'Grafana', 'icon' => 'devicon:grafana', 'proficiency' => 3, 'group' => 'devops', 'order' => 3, 'resume' => false],
        ['name' => 'Zabbix', 'icon' => null, 'proficiency' => 3, 'group' => 'devops', 'order' => 4, 'resume' => false],

        ['name' => 'ChatGPT', 'icon' => null, 'proficiency' => 4, 'group' => 'ai', 'order' => 0, 'resume' => true],
        ['name' => 'Claude AI', 'icon' => null, 'proficiency' => 4, 'group' => 'ai', 'order' => 1, 'resume' => true],
        ['name' => 'DeepSeek', 'icon' => null, 'proficiency' => 3, 'group' => 'ai', 'order' => 2, 'resume' => false],
        ['name' => 'OpenCode', 'icon' => null, 'proficiency' => 3, 'group' => 'ai', 'order' => 3, 'resume' => false],

        ['name' => 'Figma', 'icon' => 'devicon:figma', 'proficiency' => 3, 'group' => 'tools', 'order' => 0, 'resume' => false],
        ['name' => 'Git', 'icon' => 'devicon:git', 'proficiency' => 5, 'group' => 'tools', 'order' => 1, 'resume' => true],
        ['name' => 'Vite', 'icon' => 'devicon:vite', 'proficiency' => 4, 'group' => 'tools', 'order' => 2, 'resume' => true],
    ],
];
