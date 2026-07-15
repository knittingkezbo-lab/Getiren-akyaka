import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Canlı güncelleme kapalıysa (Reverb'siz başlangıç) Echo'yu HİÇ başlatma —
// böylece tarayıcı boşuna WebSocket'e bağlanmaya çalışıp konsolu hata ile doldurmaz.
// AppLayout zaten `window.Echo` var mı diye baktığından abonelikler sessizce atlanır.
const liveUpdates = import.meta.env.VITE_LIVE_UPDATES !== 'false' && !!import.meta.env.VITE_REVERB_APP_KEY;

if (liveUpdates) {
    window.Pusher = Pusher;

    // Laravel Reverb (WebSocket) — canlı bildirim + sipariş güncellemeleri
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
