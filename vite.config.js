import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
  plugins: [vue()],
  // Assets are served from a configurable, potentially-nested Laravel route
  // (anima.path, default "anima/assets/..."), not the site root, so asset
  // URLs (including the worker URLs Vite emits for `new Worker(new URL(...))`)
  // must resolve relative to wherever app.js was actually loaded from.
  base: './',
  // MonacoPayloadEditor.vue creates its workers with `{ type: 'module' }`,
  // but Monaco's worker entry files use ESM import/export; Vite's default
  // worker output ('iife') can't satisfy that, so it must match here.
  worker: {
    format: 'es',
  },
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(__dirname, 'resources/js/app.js'),
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'app.css';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js'),
    },
  },
});
