<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const url = computed(() => page.url);

// Role göre kenar menü öğeleri
const navByRole = {
    customer: [
        { label: 'Panel', icon: '🏠', href: '/musteri' },
        { label: 'Yeni sipariş', icon: '➕', href: '/musteri/siparis/yeni' },
        { label: 'Siparişlerim', icon: '🧾', href: '/musteri/siparisler' },
        { label: 'Cüzdan', icon: '💳', href: '/musteri/cuzdan' },
        { label: 'Profil', icon: '👤', href: '/musteri/profil' },
    ],
    courier: [
        { label: 'İşler', icon: '🛵', href: '/kurye' },
    ],
    admin: [
        { label: 'Panel', icon: '📊', href: '/yonetici' },
        { label: 'Siparişler', icon: '🧾', href: '/yonetici/siparisler' },
        { label: 'Kuryeler', icon: '🛵', href: '/yonetici/kuryeler' },
        { label: 'Ayarlar', icon: '⚙️', href: '/yonetici/ayarlar' },
    ],
};

const nav = computed(() => navByRole[user.value?.role] ?? []);
const homeHref = computed(() => nav.value[0]?.href);

const isActive = (href) => {
    if (url.value === href) return true;
    if (href === homeHref.value) return false;
    return url.value.startsWith(href);
};

const initials = computed(() =>
    (user.value?.name ?? '?')
        .split(' ')
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const logout = () => router.post('/logout');

// Bildirimler (paylaşılan prop'tan)
const notifications = computed(() => page.props.notifications ?? { unread: 0, items: [] });
const bellOpen = ref(false);
const markAllRead = () => router.post('/bildirimler/oku', {}, { preserveScroll: true, preserveState: true });
</script>

<template>
    <div class="layout">
        <aside class="sidebar">
            <Link class="brand" :href="homeHref"><span class="brand__mark">◐</span> getiren<b>akyaka</b></Link>

            <nav class="nav">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="nav__item"
                    :class="{ 'is-active': isActive(item.href) }"
                >
                    <span class="nav__ic">{{ item.icon }}</span>
                    <span class="nav__label">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="sidebar__foot">
                <div class="usercard">
                    <span class="avatar avatar--sm">{{ initials }}</span>
                    <div style="flex:1; min-width:0">
                        <b style="font-size:14px; display:block; overflow:hidden; text-overflow:ellipsis">{{ user?.name }}</b>
                        <small>{{ user?.role_label }}</small>
                    </div>
                    <button class="btn btn--icon btn--sm" style="width:34px; height:34px" title="Çıkış" @click="logout">⎋</button>
                </div>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <div>
                    <h1>{{ title }}</h1>
                    <p v-if="subtitle" class="crumb">{{ subtitle }}</p>
                </div>
                <div class="topbar__spacer"></div>
                <slot name="actions" />

                <div class="notif-wrap">
                    <button class="btn btn--icon" title="Bildirimler" @click="bellOpen = !bellOpen">
                        🔔
                        <span v-if="notifications.unread" class="notif-badge">{{ notifications.unread }}</span>
                    </button>
                    <div v-if="bellOpen" class="notif-panel">
                        <div class="notif-head">
                            <b>Bildirimler</b>
                            <a v-if="notifications.unread" href="#" style="font-size:12.5px; font-weight:700; color:var(--primary-2)" @click.prevent="markAllRead">Tümünü okundu işaretle</a>
                        </div>
                        <div class="notif-list">
                            <Link
                                v-for="n in notifications.items"
                                :key="n.id"
                                :href="n.url || '#'"
                                class="notif-item"
                                :class="{ unread: !n.read }"
                                @click="bellOpen = false"
                            >
                                <b>{{ n.title }}</b>
                                <span class="msg">{{ n.message }}</span>
                                <small>{{ n.created_at }}</small>
                            </Link>
                            <p v-if="!notifications.items.length" class="muted" style="padding:20px; text-align:center">Henüz bildirim yok.</p>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.notif-wrap { position: relative; }
.notif-badge { position: absolute; top: -4px; right: -4px; background: var(--primary); color: #fff; font-size: 10px; font-weight: 900; min-width: 17px; height: 17px; border-radius: 999px; display: grid; place-items: center; padding: 0 4px; }
.notif-panel { position: absolute; right: 0; top: calc(100% + 8px); width: 330px; max-width: 86vw; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); z-index: 50; overflow: hidden; }
.notif-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--line); }
.notif-list { max-height: 360px; overflow: auto; }
.notif-item { display: block; padding: 12px 14px; border-bottom: 1px solid var(--line); text-decoration: none; color: inherit; }
.notif-item:last-child { border-bottom: 0; }
.notif-item:hover { background: var(--surface-2); }
.notif-item.unread { background: var(--primary-soft); }
.notif-item b { font-size: 14px; display: block; }
.notif-item .msg { font-size: 13px; color: var(--muted); }
.notif-item small { color: var(--muted); display: block; margin-top: 2px; }
</style>
