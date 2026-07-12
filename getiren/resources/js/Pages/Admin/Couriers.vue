<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({ couriers: { type: Array, default: () => [] } });

const money = (n) => Number(n).toLocaleString('tr-TR');
const initials = (name) => (name || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
const summary = computed(() => ({
    available: props.couriers.filter((c) => c.status === 'available').length,
    busy: props.couriers.filter((c) => c.status === 'busy').length,
}));
</script>

<template>
    <Head title="Kuryeler" />

    <AppLayout title="Kuryeler" subtitle="Ekip · durum ve performans">
        <div class="stack">
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
