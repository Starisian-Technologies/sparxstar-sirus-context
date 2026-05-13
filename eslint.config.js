import js from '@eslint/js';
import importPlugin from 'eslint-plugin-import';
import prettierPlugin from 'eslint-plugin-prettier';
import prettierConfig from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        ignores: [
            'node_modules/',
            'vendor/',
            '**/vendor/',
            'dist/',
            'assets/',
            'languages/',
            'tests/e2e/',
        ],
    },
    js.configs.recommended,
    importPlugin.flatConfigs.recommended,
    {
        plugins: {
            prettier: prettierPlugin,
        },
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.es2022,
                wp: 'readonly',
                jQuery: 'readonly',
            },
        },
        rules: {
            ...prettierConfig.rules,
            'prettier/prettier': 'error',
            'no-console': 'off',
            'no-unused-vars': [
                'warn',
                {
                    args: 'none',
                    vars: 'all',
                },
            ],
            'no-undef': 'error',
            'import/no-unresolved': 'off',
        },
    },
];
