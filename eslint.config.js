import pluginVue from 'eslint-plugin-vue'

export default [
    {
        files: ['**/*.vue'],
        ...pluginVue.configs['flat/recommended'],
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'warn',
            'vue/require-v-for-key': 'error',
            'vue/valid-v-for': 'error',
            'vue/no-mutating-props': 'error',
        },
    },
]
