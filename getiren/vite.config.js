import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

const appEntry = fileURLToPath(new URL('./resources/js/app.js', import.meta.url));

/**
 * Inertia sayfaları app.js içinde import.meta.glob ile çözülüyor ve bu glob transform
 * anında sabitleniyor. Docker bind-mount'ta "dosya eklendi" olayı glob'u kendiliğinden
 * geçersizleştirmediği için yeni sayfa, sunucu yeniden başlatılana kadar bulunamıyor —
 * belirtisi konsolda hatasız bembeyaz sayfa. Yeni/silinen sayfada app.js'i tazeliyoruz.
 */
const refreshPagesGlob = () => ({
    name: 'getiren:refresh-pages-glob',
    configureServer(server) {
        const invalidateAppEntry = (file) => {
            if (! file.replaceAll('\\', '/').includes('/resources/js/Pages/')) {
                return;
            }

            const graph = server.environments?.client?.moduleGraph ?? server.moduleGraph;

            // Dosya yolundan git: modül kimliği url biçiminde tutulduğu için getModuleById burada boş döner
            graph?.getModulesByFile?.(appEntry)?.forEach((mod) => graph.invalidateModule(mod));

            (server.hot ?? server.ws).send({ type: 'full-reload', path: '*' });
        };

        server.watcher.on('add', invalidateAppEntry);
        server.watcher.on('unlink', invalidateAppEntry);
    },
});

export default defineConfig({
    plugins: [
        refreshPagesGlob(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Tarayıcı (host) dev sunucusuna localhost üzerinden bağlanır
        hmr: { host: 'localhost' },
        // Docker bind-mount'ta dosya değişikliklerini yakalamak için
        watch: {
            usePolling: true,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
