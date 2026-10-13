import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            'WebSdk': path.resolve(__dirname, 'resources/js/lib/fingerprint/websdk-shim.ts'),
            'next/link': path.resolve(__dirname, 'resources/js/lib/next-link.tsx'),
            'next/navigation': path.resolve(__dirname, 'resources/js/lib/next-navigation.tsx'),
            'next/image': path.resolve(__dirname, 'resources/js/lib/next-image.tsx'),
        },
    },
});
