import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const inDocker = process.env.VITE_IN_DOCKER === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    server: inDocker
        ? {
              host: '0.0.0.0',
              port: 5173,
              strictPort: true,
              origin: 'http://localhost:5173',
              cors: true,
              hmr: { host: 'localhost' },
              // Bind mounts from Windows/macOS don't deliver inotify events.
              watch: { usePolling: true, interval: 300, ignored: ['**/vendor/**', '**/storage/**', '**/node_modules/**'] },
          }
        : undefined,
});
