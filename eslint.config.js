import js from '@eslint/js'
import globals from 'globals'

export default [
    js.configs.recommended,
    {
        files: ['resources/assets/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: globals.browser,
        },
        rules: {
            'no-unused-vars': ['error', { args: 'after-used', varsIgnorePattern: '^_' }],
            'no-console': ['error', { allow: ['error'] }],
            eqeqeq: 'error',
            'prefer-const': 'error',
            'no-var': 'error',
            'object-shorthand': 'error',
            'no-else-return': 'error',
        },
    },
    {
        files: ['tests/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: globals.node,
        },
    },
]
