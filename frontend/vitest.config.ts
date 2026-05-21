import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/**/*.spec.ts'],
    setupFiles: ['tests/setup.ts'],
    coverage: {
      reporter: ['text', 'html'],
      include: ['src/**/*.{ts,vue}'],
      exclude: ['src/main.ts', 'src/router/**', 'src/types/**'],
    },
  },
});
