<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

defineProps({
    available: { type: Array, default: () => [] },
    mine: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
});

const firstName = computed(() => (usePage().props.auth?.user?.name ?? '').split(' ')[0]);
const money = (n) => Number(n).toLocaleString('tr-TR');

const badgeClass = (s) =>
    ({ assigned: 'badge--primary', shopping: 'badge--primary', on_the_way: 'badge--amber' })[s] ?? 'badge--muted';

const accept = (id) => router.post(`/kurye/is/${id}/ustlen`);
</script>

<template>
    <Head title="İşler" />

    <AppLayout :title="`Merhaba ${firstName} 🛵`" subtitle="Müsait işleri üstlen, aktif işlerini yönet.">
        <div class="stack" style="gap:20px">
            <!-- özet -->
            <div class="stats" style="grid-template-columns:repeat(3,1fr)">
                <div class="stat stat--primary"><div class="k">Bugünkü kazanç</div><div class="v">{{ money(stats.earnings_today) }} <small>TL</small></div></div>
                <div class="stat"><div class="k">Bugün teslim</div><div class="v">{{ stats.delivered_today }}</div></div>
                <div class="stat"><div class="k">Aktif iş</div><div class="v">{{ stats.active }}</div></div>
            </div>

            <!-- aktif işlerim -->
            <div class="card" v-if="mine.length">
                <div class="card__head"><div><p class="eyebrow">Aktif işlerim</p><h2>Üzerinde çalıştıkların</h2></div></div>
                <div class="stack-sm">
                    <div v-for="o in mine" :key="o.id" class="list__row">
                        <div class="media">
                            <span class="avatar avatar--sm" style="background:var(--surface-3); color:var(--ink-soft)">📦</span>
                            <div><b>#{{ o.code }} · {{ o.raw_text }}</b><small class="muted">{{ o.zone_name }} · {{ o.customer_name }}</small></div>
                        </div>
                        <div class="row">
                            <span class="badge" :class="badgeClass(o.status)">{{ o.status_label }}</span>
                            <Link class="btn btn--primary btn--sm" :href="`/kurye/is/${o.id}`">Devam et →</Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- müsait işler -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Müsait işler</p><h2>Yakınındaki siparişler</h2></div>
                    <span class="badge badge--sage">{{ available.length }} açık</span></div>

                <div v-if="available.length" class="stack-sm">
                    <div v-for="o in available" :key="o.id" class="list__row">
                        <div class="media">
                            <span class="avatar avatar--sm" style="background:var(--surface-3); color:var(--ink-soft)">🧺</span>
                            <div><b>{{ o.raw_text }}</b><small class="muted">{{ o.zone_name }} · {{ o.customer_name }} · {{ o.created_at }}</small></div>
                        </div>
                        <div class="row">
                            <span class="badge badge--muted">Provizyon {{ money(o.reserved_amount) }} TL</span>
                            <button class="btn btn--primary btn--sm" @click="accept(o.id)">Üstlen</button>
                        </div>
                    </div>
                </div>
                <div v-else class="empty">
                    <div class="em">☕</div>
                    <h3 style="margin:8px 0 4px">Şu an müsait iş yok</h3>
                    <p class="muted">Yeni siparişler geldikçe burada görünecek.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
