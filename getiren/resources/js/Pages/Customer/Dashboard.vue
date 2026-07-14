<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, required: true },
    activeOrder: { type: Object, default: null },
    recentOrders: { type: Array, default: () => [] },
});

const firstName = computed(() => (usePage().props.auth?.user?.name ?? '').split(' ')[0]);
const money = (n) => Number(n).toLocaleString('tr-TR');

// Sipariş durum çizelgesi
const steps = [
    { key: 'reserved', label: 'Provizyon' },
    { key: 'assigned', label: 'Kurye atandı' },
    { key: 'shopping', label: 'Alışverişte' },
    { key: 'on_the_way', label: 'Yolda' },
    { key: 'delivered', label: 'Teslim' },
];
const statusOrder = { reserved: 0, assigned: 1, shopping: 2, on_the_way: 3, delivered: 4 };
const currentStep = computed(() => statusOrder[props.activeOrder?.status] ?? 0);
const stepState = (i) => (i < currentStep.value ? 'done' : i === currentStep.value ? 'now' : '');

const badgeClass = (status) =>
    ({
        reserved: 'badge--muted',
        assigned: 'badge--primary',
        shopping: 'badge--primary',
        on_the_way: 'badge--amber',
        delivered: 'badge--sage',
        requires_extra_payment: 'badge--danger',
        cancelled: 'badge--muted',
    })[status] ?? 'badge--muted';
</script>

<template>
    <Head title="Panel" />

    <AppLayout :title="`Merhaba ${firstName} 👋`" subtitle="Bugün ne getirelim?">
        <template #actions>
            <div v-if="stats.open_authorized > 0" class="walletchip">
                <span class="muted">Açık provizyon</span> <b>{{ money(stats.open_authorized) }} TL</b>
            </div>
            <Link class="btn btn--soft btn--sm" href="/musteri/odemeler">Ödemelerim</Link>
        </template>

        <div class="stack" style="gap:20px">
            <!-- hero CTA -->
            <Link
                href="/musteri/siparis/yeni"
                class="card"
                style="background:linear-gradient(150deg,var(--primary),#e6863f); color:#fff; border:0; text-decoration:none; display:block"
            >
                <div class="spread" style="align-items:center; flex-wrap:wrap; gap:16px">
                    <div style="max-width:44ch">
                        <p class="eyebrow" style="color:#ffe1cf">Yeni sipariş</p>
                        <h2 style="color:#fff; font-size:27px; margin:4px 0 6px">Ne lazımsa yaz, gerisini bize bırak.</h2>
                        <p style="color:#ffe7d8">Tutarı provizyona alırız, gerçek fişe göre keser, fazlasını iade ederiz.</p>
                    </div>
                    <span class="btn" style="background:#fff; color:var(--primary-2)">Sipariş oluştur →</span>
                </div>
            </Link>

            <!-- aktif sipariş -->
            <div v-if="activeOrder" class="card">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Aktif sipariş · #{{ activeOrder.code }}</p>
                        <h2>{{ activeOrder.raw_text }}</h2>
                    </div>
                    <span class="badge" :class="badgeClass(activeOrder.status)"><span class="dot"></span> {{ activeOrder.status_label }}</span>
                </div>
                <div class="steps">
                    <div v-for="(step, i) in steps" :key="step.key" class="step" :class="stepState(i)">
                        <div class="bul">{{ i < currentStep ? '✓' : i === currentStep ? '●' : i + 1 }}</div>
                        <small>{{ step.label }}</small>
                    </div>
                </div>
                <hr class="ticket__perf" />
                <div class="spread">
                    <div class="media">
                        <span class="avatar avatar--sm">{{ (activeOrder.courier_name || '—').slice(0, 1) }}</span>
                        <div>
                            <b>{{ activeOrder.courier_name || 'Kurye atanıyor' }}</b>
                            <small class="muted">{{ activeOrder.zone_name }} · Provizyon {{ money(activeOrder.reserved_amount) }} TL</small>
                        </div>
                    </div>
                    <Link class="btn btn--ghost btn--sm" :href="`/musteri/siparisler/${activeOrder.id}`">Takip et →</Link>
                </div>
            </div>
            <div v-else class="empty">
                <div class="em">🧺</div>
                <h3 style="margin:8px 0 4px">Aktif siparişin yok</h3>
                <p class="muted">Yeni bir sipariş oluşturarak başla.</p>
                <Link class="btn btn--primary btn--sm" href="/musteri/siparis/yeni" style="margin-top:12px">Sipariş oluştur</Link>
            </div>

            <!-- istatistikler -->
            <div class="stats">
                <div class="stat"><div class="k">🔒 Açık provizyon</div><div class="v">{{ money(stats.open_authorized) }} <small>TL</small></div></div>
                <div class="stat"><div class="k">💳 Bu ay ödenen</div><div class="v">{{ money(stats.month_captured) }} <small>TL</small></div></div>
                <div class="stat"><div class="k">📦 Bu ay</div><div class="v">{{ stats.month_count }} <small>sipariş</small></div></div>
            </div>

            <!-- son siparişler -->
            <div class="card card--pad-sm">
                <div class="card__head" style="margin-bottom:4px">
                    <h3>Son siparişler</h3>
                    <Link href="/musteri/siparisler" class="hint" style="font-weight:800; color:var(--primary-2)">Tümü →</Link>
                </div>

                <div v-if="recentOrders.length" class="stack-sm">
                    <Link
                        v-for="o in recentOrders"
                        :key="o.code"
                        :href="`/musteri/siparisler/${o.id}`"
                        class="list__row"
                        style="text-decoration:none; color:inherit"
                    >
                        <div class="media">
                            <span class="avatar avatar--sm" style="background:var(--surface-3); color:var(--ink-soft)">🧾</span>
                            <div>
                                <b>{{ o.raw_text }}</b>
                                <small class="muted">{{ o.zone_name }} · {{ o.created_at }}</small>
                            </div>
                        </div>
                        <div class="row">
                            <span class="badge" :class="badgeClass(o.status)">{{ o.status_label }}</span>
                            <b class="num">{{ money(o.reserved_amount) }} TL</b>
                        </div>
                    </Link>
                </div>
                <p v-else class="muted" style="padding:12px 2px">Henüz siparişin yok.</p>
            </div>
        </div>
    </AppLayout>
</template>
