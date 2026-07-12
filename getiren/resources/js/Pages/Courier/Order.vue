<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({ order: { type: Object, required: true } });
const money = (n) => Number(n).toLocaleString('tr-TR');

const form = useForm({
    items: props.order.items.map((i) => ({ id: i.id, actual_price: i.actual_price })),
});
const itemMeta = (id) => props.order.items.find((i) => i.id === id) ?? {};

const fisTotal = computed(() => form.items.reduce((s, it) => s + (Number(it.actual_price) || 0), 0));
const captured = computed(() => fisTotal.value + props.order.service_fee);
const isOver = computed(() => captured.value > props.order.reserved_amount);
const refund = computed(() => Math.max(0, props.order.reserved_amount - captured.value));
const extra = computed(() => Math.max(0, captured.value - props.order.reserved_amount));

const advance = () => router.post(`/kurye/is/${props.order.id}/durum`);
const settle = () => form.post(`/kurye/is/${props.order.id}/fis`);
</script>

<template>
    <Head :title="`İş #${order.code}`" />

    <AppLayout :title="`İş #${order.code}`" :subtitle="order.status_label">
        <template #actions>
            <span class="badge badge--primary"><span class="dot"></span> {{ order.status_label }}</span>
        </template>

        <div class="grid col-wide" style="align-items:start">
            <!-- sol -->
            <div class="stack">
                <!-- müşteri -->
                <div class="card card--pad-sm">
                    <div class="spread">
                        <div class="media">
                            <span class="avatar">{{ (order.customer_name || '?').slice(0, 1) }}</span>
                            <div><b>{{ order.customer_name }}</b><small class="muted">Müşteri · {{ order.customer_phone }}</small></div>
                        </div>
                        <a class="btn btn--soft btn--sm" href="#">📞 Ara</a>
                    </div>
                    <hr class="ticket__perf" />
                    <div class="stack-sm" style="gap:6px; font-size:14px">
                        <div class="spread"><span class="muted">📍 Teslimat</span><b>{{ order.zone_name }} · {{ order.address_text }}</b></div>
                        <div v-if="order.customer_note" class="spread"><span class="muted">📝 Not</span><b>{{ order.customer_note }}</b></div>
                    </div>
                </div>

                <!-- kalemler -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Kalemler</p><h2>Fiş fiyatlarını gir</h2></div></div>
                    <div class="stack-sm">
                        <div v-for="it in form.items" :key="it.id" class="list__row">
                            <div class="media">
                                <span class="avatar avatar--sm" style="background:var(--surface-3); color:var(--ink-soft)">🛒</span>
                                <div>
                                    <b>{{ itemMeta(it.id).name }}</b>
                                    <small class="muted">{{ itemMeta(it.id).qty }} adet · tahmini {{ money(itemMeta(it.id).estimated_price) }} TL</small>
                                </div>
                            </div>
                            <div class="input-group" style="width:150px">
                                <input class="input" type="number" min="0" v-model.number="it.actual_price" />
                                <span class="addon">TL</span>
                            </div>
                        </div>
                    </div>
                    <hr class="ticket__perf" />
                    <div class="spread"><b>Fiş ara toplamı</b><b class="num" style="font-family:var(--serif); font-size:20px">{{ money(fisTotal) }} TL</b></div>
                </div>

                <!-- durum ilerlet -->
                <div class="card" v-if="order.can_advance">
                    <div class="spread">
                        <div><h3>Sırada</h3><p class="hint" style="margin-top:2px">İşi bir sonraki adıma taşı.</p></div>
                        <button class="btn btn--ghost" @click="advance">{{ order.next_label }} →</button>
                    </div>
                </div>
            </div>

            <!-- sağ: kapatma -->
            <div class="order-summary">
                <div class="ticket">
                    <p class="eyebrow">Siparişi kapat</p><h3 style="margin:2px 0 4px">Tahsilat</h3>
                    <hr class="ticket__perf" />
                    <div class="ticket__row"><span>Bloke edilen</span><b>{{ money(order.reserved_amount) }} TL</b></div>
                    <div class="ticket__row"><span>Fiş (ürünler)</span><b>{{ money(fisTotal) }} TL</b></div>
                    <div class="ticket__row"><span>Hizmet bedeli</span><b>{{ money(order.service_fee) }} TL</b></div>
                    <hr class="ticket__perf" />
                    <div class="ticket__row ticket__row--total"><span>Tahsil edilecek</span><b>{{ money(captured) }} TL</b></div>
                    <div v-if="!isOver" class="ticket__row"><span>Müşteriye iade</span><b class="num" style="color:#3f7a4a">+{{ money(refund) }} TL</b></div>
                    <div v-else class="ticket__row"><span>Ek ödeme</span><b class="num" style="color:var(--danger)">{{ money(extra) }} TL</b></div>

                    <div v-if="isOver" class="alert alert--warn" style="margin-top:12px">
                        <span class="alert__ic">!</span>
                        <div>Fiş blokeyi aştı. Kapatınca sipariş <b>ek ödeme bekliyor</b> olur.</div>
                    </div>

                    <button class="btn btn--primary btn--block btn--lg" style="margin-top:14px" :disabled="!order.can_settle || form.processing" @click="settle">
                        {{ form.processing ? 'Kapatılıyor…' : 'Fiş gir ve kapat' }}
                    </button>
                    <Link href="/kurye" class="btn btn--ghost btn--block" style="margin-top:8px">İşlere dön</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
