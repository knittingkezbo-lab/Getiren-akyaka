<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({ order: { type: Object, required: true } });

const money = (n) => Number(n).toLocaleString('tr-TR');

const steps = [
    { key: 'reserved', label: 'Bloke' },
    { key: 'assigned', label: 'Kurye atandı' },
    { key: 'shopping', label: 'Alışverişte' },
    { key: 'on_the_way', label: 'Yolda' },
    { key: 'delivered', label: 'Teslim' },
];
const statusOrder = { reserved: 0, assigned: 1, shopping: 2, on_the_way: 3, delivered: 4, requires_extra_payment: 4 };
const currentStep = computed(() => statusOrder[props.order.status] ?? 0);
const isCancelled = computed(() => props.order.status === 'cancelled');
const stepState = (i) => {
    if (isCancelled.value) return '';
    return i < currentStep.value ? 'done' : i === currentStep.value ? 'now' : '';
};

const badgeClass = (s) =>
    ({
        reserved: 'badge--muted',
        assigned: 'badge--primary',
        shopping: 'badge--primary',
        on_the_way: 'badge--amber',
        delivered: 'badge--sage',
        requires_extra_payment: 'badge--danger',
        cancelled: 'badge--muted',
    })[s] ?? 'badge--muted';

const confirming = ref(false);
const cancel = () => {
    if (!confirming.value) {
        confirming.value = true;
        return;
    }
    router.post(`/musteri/siparisler/${props.order.id}/iptal`);
};
</script>

<template>
    <Head title="Sipariş takibi" />

    <AppLayout title="Sipariş takibi" :subtitle="`#${order.code} · ${order.zone_name}`">
        <template #actions>
            <span class="badge" :class="badgeClass(order.status)"><span class="dot"></span> {{ order.status_label }}</span>
        </template>

        <div class="grid col-wide" style="align-items:start">
            <!-- sol -->
            <div class="stack">
                <div class="card card--pad-sm">
                    <div class="map"><span class="route"></span><span class="home">🏠</span><span class="pin">📍</span></div>
                    <div class="spread" style="margin-top:14px">
                        <div class="media">
                            <span class="avatar">{{ (order.courier_name || '—').slice(0, 1) }}</span>
                            <div>
                                <b>{{ order.courier_name || 'Kurye atanıyor' }}</b>
                                <small class="muted">{{ order.zone_name }}{{ order.courier_name ? ' · 4.9 ★' : '' }}</small>
                            </div>
                        </div>
                        <div class="row" v-if="order.courier_name">
                            <a class="btn btn--ghost btn--sm" href="#">💬 Mesaj</a>
                            <a class="btn btn--soft btn--sm" href="#">📞 Ara</a>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 style="margin-bottom:16px">Sipariş durumu</h3>

                    <div v-if="isCancelled" class="alert alert--danger">
                        <span class="alert__ic">✕</span>
                        <div>Bu sipariş iptal edildi; bloke çözüldü.</div>
                    </div>
                    <template v-else>
                        <div class="steps">
                            <div v-for="(s, i) in steps" :key="s.key" class="step" :class="stepState(i)">
                                <div class="bul">{{ i < currentStep ? '✓' : i === currentStep ? '●' : i + 1 }}</div>
                                <small>{{ s.label }}</small>
                            </div>
                        </div>
                        <div v-if="order.status === 'requires_extra_payment'" class="alert alert--warn" style="margin-top:18px">
                            <span class="alert__ic">!</span>
                            <div>Fiş blokeyi aştı — <b>{{ money(order.extra_required_amount) }} TL</b> ek ödeme gerekiyor.</div>
                        </div>
                        <div v-else-if="order.status === 'delivered'" class="alert alert--ok" style="margin-top:18px">
                            <span class="alert__ic">✓</span>
                            <div>Teslim edildi. Fazla blokaj <b>{{ money(order.refund_amount || 0) }} TL</b> cüzdanına iade edildi.</div>
                        </div>
                        <div v-else class="alert alert--info" style="margin-top:18px">
                            <span class="alert__ic">🛒</span>
                            <div>Siparişin hazırlanıyor. Ürünler bulundukça gerçek fiyatlar fişe işlenir.</div>
                        </div>
                    </template>

                    <template v-if="order.customer_note">
                        <hr class="ticket__perf" />
                        <div class="spread" style="font-size:14px"><span class="muted">📝 Not</span><b>{{ order.customer_note }}</b></div>
                    </template>
                </div>
            </div>

            <!-- sağ: fiş -->
            <div class="ticket">
                <div class="spread"><div><p class="eyebrow">Sipariş #{{ order.code }}</p><h3 style="margin-top:2px">{{ order.zone_name }}</h3></div></div>
                <hr class="ticket__perf" />
                <div class="stack-sm" style="gap:6px">
                    <div v-for="(it, idx) in order.items" :key="idx" class="ticket__row">
                        <span>{{ it.qty }}× {{ it.name }}</span>
                        <b>{{ it.actual_price != null ? money(it.actual_price) : '~' + money(it.estimated_price) }} TL</b>
                    </div>
                </div>
                <hr class="ticket__perf" />
                <div class="ticket__row">
                    <span>Ürün {{ order.actual_receipt_amount != null ? '(fiş)' : '(tahmini)' }}</span>
                    <b>{{ money(order.actual_receipt_amount ?? order.items_total) }} TL</b>
                </div>
                <div class="ticket__row"><span>Güvenlik payı</span><b>{{ money(order.safety_buffer) }} TL</b></div>
                <div class="ticket__row"><span>Teslimat</span><b>{{ money(order.service_fee) }} TL</b></div>
                <hr class="ticket__perf" />
                <div class="ticket__row ticket__row--total">
                    <span>{{ order.captured_amount != null ? 'Tahsil edilen' : 'Bloke' }}</span>
                    <b>{{ money(order.captured_amount ?? order.reserved_amount) }} TL</b>
                </div>
                <div v-if="order.refund_amount" class="ticket__row">
                    <span>İade edilen</span><b class="num" style="color:#3f7a4a">+{{ money(order.refund_amount) }} TL</b>
                </div>

                <div class="ticket__stub" style="margin-top:10px">
                    <span class="badge badge--sage">Fazlası iade</span>
                    <p class="hint" style="margin:0">Fiş kesinleşince fark cüzdanına döner.</p>
                </div>

                <button
                    v-if="order.can_cancel"
                    class="btn btn--block"
                    :class="confirming ? 'btn--danger' : 'btn--ghost'"
                    style="margin-top:16px"
                    @click="cancel"
                >
                    {{ confirming ? 'Emin misin? İptali onayla' : 'Siparişi iptal et' }}
                </button>
                <p v-if="confirming" class="hint" style="text-align:center; margin-top:8px">
                    <a href="#" style="color:var(--primary-2); font-weight:700" @click.prevent="confirming = false">Vazgeç</a>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
