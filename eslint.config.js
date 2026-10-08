import vueParser from 'vue-eslint-parser';
import tsParser from '@typescript-eslint/parser';

export default [
  {
    ignores: [
      'vendor/**',
      'public/**',
      'node_modules/**',
      'bootstrap/cache/**',
      'storage/**',
    ],
  },
  {
    files: ['resources/js/**/*.ts'],
    languageOptions: {
      parser: tsParser,
      ecmaVersion: 'latest',
      sourceType: 'module',
    },
    rules: {
      'no-unused-vars': 'off',
      'no-console': 'off',
    },
  },
  {
    files: ['resources/js/**/*.vue'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: tsParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
      },
    },
    rules: {
      'no-unused-vars': 'off',
      'no-console': 'off',
    },
  },
];
