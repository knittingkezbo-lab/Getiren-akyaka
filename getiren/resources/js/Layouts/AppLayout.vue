<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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
            </header>

            <div class="content">
                <slot />
            </div>
        </div>
    </div>
</template>
