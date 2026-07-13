import { router } from '@inertiajs/vue3';

// Modül düzeyi tekil timer — belirlenen süre boyunca hiç etkileşim olmazsa
// güvenlik için otomatik çıkış. Reaktiviteye bağlı değil (saf setTimeout) → sağlam.
let logoutTimer = null;
let lastActivity = 0;
let listening = false;
let idleMinutes = 30;

const clearTimer = () => {
    clearTimeout(logoutTimer);
    logoutTimer = null;
};

const schedule = () => {
    clearTimer();
    logoutTimer = setTimeout(doLogout, Math.max(2, idleMinutes) * 60000);
};

const doLogout = () => {
    clearTimer();
    try { localStorage.setItem('idle_logout', '1'); } catch (e) { /* yoksay */ }
    router.post('/logout');
};

const onActivity = () => {
    const t = Date.now();
    if (t - lastActivity > 5000) { // en fazla 5 sn'de bir yeniden zamanla
        lastActivity = t;
        schedule();
    }
};

const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

/** Hareketsizlik takibini başlat (AppLayout mount'ta çağrılır; birden çok çağrı güvenli). */
export function startIdleLogout(minutes) {
    idleMinutes = minutes || 30;
    if (!listening) {
        listening = true;
        events.forEach((e) => window.addEventListener(e, onActivity, { passive: true }));
    }
    schedule();
}

/** Takibi durdur (elle çıkışta çağrılır — yetim timer kalmasın). */
export function stopIdleLogout() {
    clearTimer();
}
