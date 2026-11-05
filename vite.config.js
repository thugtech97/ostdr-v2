import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
    // Relative in the build so page chunks load from wherever the app is hosted, including a
    // subfolder. The default "/build/" would look for them at the domain root.
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
}));
