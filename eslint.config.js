import tsParser from '@typescript-eslint/parser'
import pluginVue from 'eslint-plugin-vue'

const recommended = pluginVue.configs['flat/recommended']

export default [
    {
        files: ['**/*.vue'],
        // `flat/recommended` is a config array — flatten its plugins,
        // language options and rule sets into a single per-file config
        // (a bare object spread would produce numeric keys and break
        // ESLint). The `vue/vue` processor is required: it filters the
        // `vue/comment-directive` sentinel reports.
        processor: 'vue/vue',
        plugins: {
            ...recommended.reduce((all, config) => ({ ...all, ...(config.plugins ?? {}) }), {}),
        },
        languageOptions: {
            ...recommended.reduce((all, config) => ({ ...all, ...(config.languageOptions ?? {}) }), {}),
            parserOptions: {
                parser: tsParser,
            },
        },
        rules: {
            ...recommended.reduce((all, config) => ({ ...all, ...(config.rules ?? {}) }), {}),
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            'vue/require-v-for-key': 'error',
            'vue/valid-v-for': 'error',
            'vue/no-mutating-props': 'error',
        },
    },
]
