export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'type-enum': [2, 'always', ['feat', 'fix', 'chore', 'docs', 'style', 'refactor', 'perf', 'test']],
        // The feature namespaces (one per IMPLEMENTATION-PLAN step) plus
        // the shared cross-feature scope and the tooling concerns used
        // across the repository history.
        'scope-enum': [
            2,
            'always',
            [
                'admin',
                'analytics',
                'api',
                'auth',
                'backup',
                'blog',
                'ci',
                'deps',
                'devops',
                'e2e',
                'docker',
                'experience',
                'experience-admin',
                'i18n',
                'makefile',
                'openapi',
                'portfolio',
                'projects',
                'resume',
                'settings',
                'shared',
                'techskills',
                'tools',
            ],
        ],
    },
};
