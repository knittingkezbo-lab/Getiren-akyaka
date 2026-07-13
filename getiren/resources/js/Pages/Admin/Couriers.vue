<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    couriers: { type: Array, default: () => [] },
    pending: { type: Array, default: () => [] },
});

const money = (n) => Number(n).toLocaleString('tr-TR');
const initials = (name) => (name || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
const summary = computed(() => ({
    available: props.couriers.filter((c) => c.status === 'available').length,
    busy: props.couriers.filter((c) => c.status === 'busy').length,
}));

const approve = (id) => router.post(`/yonetici/kuryeler/${id}/onayla`, {}, { preserveScroll: true });
const reject = (id) => router.post(`/yonetici/kuryeler/${id}/reddet`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Kuryeler" />

    <AppLayout title="Kuryeler" subtitle="Ekip · durum ve performans">
        <div class="stack">
            <!-- onay bekleyen kurye başvuruları -->
            <div v-if="pending.length" class="card pending">
                <div class="card__head"><div><p class="eyebrow">Onay bekliyor</p><h2>Kurye başvuruları ({{ pending.length }})</h2></div></div>
                <div v-for="p in pending" :key="p.id" class="pending-row">
                    <div class="media"><span class="avatar">{{ initials(p.name) }}</span><div><b>{{ p.name }}</b><small class="muted">{{ p.email }} · {{ p.phone || '—' }} · {{ p.applied_at }}</small></div></div>
                    <div class="row" style="gap:8px">
                        <button class="btn btn--sm" @click="reject(p.id)">Reddet</button>
                        <button class="btn btn--primary btn--sm" @click="approve(p.id)">Onayla</button>
                    </div>
                </div>
            </div>

            <div class="stats" style="grid-template-columns:repeat(3,1fr)">
                <div class="stat"><div class="k">🟢 Müsait</div><div class="v">{{ summary.available }}</div></div>
                <div class="stat"><div class="k">🟠 Meşgul</div><div class="v">{{ summary.busy }}</div></div>
                <div class="stat"><div class="k">Toplam kurye</div><div class="v">{{ couriers.length }}</div></div>
            </div>

            <div class="grid cols-3">
                <div v-for="c in couriers" :key="c.id" class="card">
                    <div class="spread">
                        <div class="media"><span class="avatar">{{ initials(c.name) }}</span><div><b>{{ c.name }}</b><small class="muted">{{ c.phone }}</small></div></div>
                        <span class="badge" :class="c.status === 'available' ? 'badge--sage' : 'badge--amber'">{{ c.status === 'available' ? 'Müsait' : 'Meşgul' }}</span>
                    </div>
                    <hr class="ticket__perf" />
                    <div class="spread" style="font-size:14px"><span class="muted">Aktif iş</span><b>{{ c.active }}</b></div>
                    <div class="spread" style="font-size:14px"><span class="muted">Bugün teslim</span><b>{{ c.delivered_today }}</b></div>
                    <div class="spread" style="font-size:14px"><span class="muted">Bugün kazanç</span><b>{{ money(c.earnings_today) }} TL</b></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.pending { border: 1.5px solid var(--primary); }
.pending-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid var(--line); }
.pending-row:first-of-type { border-top: 0; }
</style>
