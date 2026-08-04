export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        'type-enum': [2, 'always', ['feat', 'fix', 'chore', 'docs', 'style', 'refactor', 'perf', 'test']],
        'scope-enum': [2, 'always', ['auth', 'blog', 'projects', 'analytics', 'admin', 'api', 'deps', 'docker', 'ci']],
    },
};
