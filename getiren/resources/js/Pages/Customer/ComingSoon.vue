<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    pageTitle: { type: String, default: 'Yakında' },
});
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :title="pageTitle" subtitle="Bu ekran yakında geliyor">
        <div class="empty">
            <div class="em">🚧</div>
            <h3 style="margin:8px 0 4px">{{ pageTitle }} · yakında</h3>
            <p class="muted">Bu ekranı sıradaki adımlarda Version 3 tasarımıyla ekleyeceğiz.</p>
        </div>
    </AppLayout>
</template>
