<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    kpis: { type: Object, required: true },
    chart: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    pending: { type: Array, default: () => [] },
    couriers: { type: Array, default: () => [] },
});

const money = (n) => Number(n).toLocaleString('tr-TR');
const maxCount = computed(() => Math.max(1, ...props.chart.map((d) => d.count)));
const peakIdx = computed(() => props.chart.reduce((mi, d, i, a) => (d.count > a[mi].count ? i : mi), 0));
const chartTotal = computed(() => props.chart.reduce((s, d) => s + d.count, 0));
const zoneTotal = computed(() => props.zones.reduce((s, z) => s + z.today_count, 0));

const assign = (orderId, e) => {
    const courierId = e.target.value;
    if (courierId) router.post(`/yonetici/siparisler/${orderId}/ata`, { courier_id: courierId }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Yönetici Paneli" />

    <AppLayout title="Yönetici paneli" subtitle="Bugünkü akış, tutarlar ve atamalar">
        <div class="stack" style="gap:20px">
            <!-- KPI -->
            <div class="stats" style="grid-template-columns:repeat(4,1fr)">
                <div class="stat stat--primary"><div class="k">Bugünkü sipariş</div><div class="v">{{ kpis.today_orders }}</div></div>
                <div class="stat"><div class="k">Ciro (tahsil)</div><div class="v">{{ money(kpis.revenue_today) }} <small>TL</small></div></div>
                <div class="stat"><div class="k">Bloke tutar</div><div class="v">{{ money(kpis.blocked_total) }} <small>TL</small></div></div>
                <div class="stat"><div class="k">Kurye</div><div class="v">{{ kpis.couriers }}</div></div>
            </div>

            <div class="grid col-wide" style="align-items:start">
                <!-- grafik -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Son 7 gün</p><h2>Günlük sipariş</h2></div><span class="badge badge--sage">Σ {{ chartTotal }}</span></div>
                    <div class="bars">
                        <div v-for="(d, i) in chart" :key="i" class="bar" :class="{ 'is-peak': i === peakIdx }">
                            <i :style="`height:${Math.max(6, (d.count / maxCount) * 100)}%`"></i>
                            <small>{{ d.label }}</small>
                        </div>
                    </div>
                </div>

                <!-- bölge dağılımı -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Bölge dağılımı</p><h2>Bugün</h2></div></div>
                    <div class="stack" style="gap:14px">
                        <div v-for="z in zones" :key="z.id">
                            <div class="spread" style="margin-bottom:6px">
                                <b>{{ z.name }}</b>
                                <span class="muted">{{ z.today_count }}<span v-if="zoneTotal"> · %{{ Math.round((z.today_count / zoneTotal) * 100) }}</span></span>
                            </div>
                            <div class="progress"><i :style="`width:${zoneTotal ? (z.today_count / zoneTotal) * 100 : 0}%`"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- bekleyen atamalar -->
            <div class="card">
                <div class="card__head">
                    <div><p class="eyebrow">Bekleyen atamalar</p><h2>Kurye bekleyen siparişler</h2></div>
                    <a href="/yonetici/siparisler" class="hint" style="font-weight:800; color:var(--primary-2)">Tümü →</a>
                </div>
                <div v-if="pending.length" class="stack-sm">
                    <div v-for="o in pending" :key="o.id" class="list__row">
                        <div class="media">
                            <span class="avatar avatar--sm" style="background:var(--surface-3); color:var(--ink-soft)">🧾</span>
                            <div><b>#{{ o.code }} · {{ o.raw_text }}</b><small class="muted">{{ o.zone_name }} · {{ o.customer_name }} · {{ o.created_at }}</small></div>
                        </div>
                        <div class="row">
                            <span class="badge badge--muted">{{ money(o.reserved_amount) }} TL</span>
                            <select class="select" style="width:170px; padding:9px 34px 9px 12px" @change="assign(o.id, $event)">
                                <option value="">Kurye ata…</option>
                                <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div v-else class="empty">
                    <div class="em">✅</div>
                    <h3 style="margin:8px 0 4px">Bekleyen atama yok</h3>
                    <p class="muted">Tüm siparişler bir kuryeye atanmış.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
