import js from '@eslint/js'
import { defineConfig } from 'eslint/config'
import globals from 'globals'
import tseslint from 'typescript-eslint'

export default defineConfig(
    { ignores: ['website/**'] },
    js.configs.recommended,
    tseslint.configs.recommendedTypeChecked,
    {
        languageOptions: {
            parserOptions: {
                projectService: {
                    allowDefaultProject: ['bundle.ts'],
                    defaultProject: 'tests/ts/tsconfig.json',
                },
                tsconfigRootDir: import.meta.dirname,
            },
        },
    },
    {
        files: ['resources/assets/ts/**/*.ts'],
        languageOptions: {
            globals: globals.browser,
        },
        rules: {
            '@typescript-eslint/no-unused-vars': ['error', { args: 'after-used', varsIgnorePattern: '^_' }],
            '@typescript-eslint/consistent-type-imports': 'error',
            'no-console': ['error', { allow: ['error'] }],
            eqeqeq: 'error',
            'prefer-const': 'error',
            'no-var': 'error',
            'object-shorthand': 'error',
            'no-else-return': 'error',
            'max-lines': ['error', { max: 200, skipBlankLines: true, skipComments: true }],
            'max-lines-per-function': ['error', { max: 20, skipBlankLines: true, skipComments: true }],
            complexity: ['error', 8],
            'max-params': ['error', 3],
            'no-nested-ternary': 'error',
        },
    },
    {
        files: ['bundle.ts', 'tests/ts/**/*.ts'],
        languageOptions: {
            globals: globals.node,
        },
        rules: {
            '@typescript-eslint/no-floating-promises': ['error', {
                allowForKnownSafeCalls: [{ from: 'package', package: 'node:test', name: ['describe', 'it'] }],
            }],
        },
    },
)
