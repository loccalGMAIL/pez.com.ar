import { defineConfig } from 'vite';

// Standalone bundle for the panel's barcode scanner. It is committed to
// resources/js/dist and published by `php artisan filament:assets`, so the
// server never needs npm and the landing build stays untouched.
export default defineConfig({
    build: {
        outDir: 'resources/js/dist',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/serial-scanner.js',
            name: 'PezSerialScanner',
            formats: ['iife'],
            fileName: () => 'serial-scanner.js',
        },
    },
});
