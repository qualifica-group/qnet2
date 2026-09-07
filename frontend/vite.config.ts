/// <reference types="vitest/config" />
import path from 'node:path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { buildVersionPlugin, resolveBuildVersion } from './vite/build-version'

// Resolved once per config load and used twice: baked into the bundle as
// `__APP_BUILD_VERSION__` (the version the client is RUNNING) and emitted to
// dist/version.json (the version currently DEPLOYED). The client compares them.
const buildVersion = resolveBuildVersion()

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss(), buildVersionPlugin(buildVersion)],
  define: {
    __APP_BUILD_VERSION__: JSON.stringify(buildVersion),
  },
  server: process.env.PORT ? { port: Number(process.env.PORT), strictPort: true } : undefined,
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'text-summary', 'html'],
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/**/*.d.ts',
        'src/**/*.test.{ts,tsx}',
        'src/**/*.spec.{ts,tsx}',
        'src/test/**',
        'src/main.tsx',
        'src/vite-env.d.ts',
      ],
      thresholds: {
        lines: 85,
        functions: 85,
        branches: 85,
        statements: 85,
      },
    },
  },
})
