import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    sourcemap: mode !== 'production',
    target: 'es2022',
    outDir: 'dist',
    emptyOutDir: true,
  },
  esbuild: {
    // Strip console + debugger in production builds (NFR-16, production hardening).
    drop: mode === 'production' ? ['console', 'debugger'] : [],
  },
}));
