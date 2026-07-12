<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filter: { type: String, default: 'all' },
});

const money = (n) => Number(n).toLocaleString('tr-TR');
const badgeClass = (s) =>
    ({
        assigned: 'badge--primary',
        shopping: 'badge--primary',
        on_the_way: 'badge--amber',
        delivered: 'badge--sage',
        requires_extra_payment: 'badge--danger',
    })[s] ?? 'badge--muted';

const tabs = [
    { key: 'all', label: 'Tümü' },
    { key: 'active', label: 'Aktif' },
    { key: 'delivered', label: 'Teslim' },
    { key: 'extra', label: 'Ek ödeme' },
    { key: 'cancelled', label: 'İptal' },
];
const setFilter = (key) =>
    router.get('/musteri/siparisler', key === 'all' ? {} : { filter: key }, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <Head title="Siparişlerim" />

    <AppLayout title="Siparişlerim" subtitle="Tüm siparişlerin ve durumları">
        <template #actions>
            <Link class="btn btn--primary btn--sm" href="/musteri/siparis/yeni">+ Yeni sipariş</Link>
        </template>

        <div class="stack">
            <div class="card card--pad-sm">
                <div class="row" style="gap:6px">
                    <span
                        v-for="t in tabs"
                        :key="t.key"
                        class="chip"
                        :class="{ 'chip--active': filter === t.key }"
                        style="cursor:pointer"
                        @click="setFilter(t.key)"
                    >{{ t.label }}</span>
                </div>
            </div>

            <div class="tablewrap">
                <table class="tbl">
                    <thead><tr><th>Sipariş</th><th>İçerik</th><th>Bölge</th><th>Durum</th><th class="num">Bloke</th><th class="num">Fiş</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="o in orders.data" :key="o.id">
                            <td><b>#{{ o.code }}</b><br /><small class="muted">{{ o.created_at }}</small></td>
                            <td>{{ o.raw_text }}</td>
                            <td>{{ o.zone_name }}</td>
                            <td><span class="badge" :class="badgeClass(o.status)">{{ o.status_label }}</span></td>
                            <td class="num">{{ money(o.reserved_amount) }}</td>
                            <td class="num">{{ o.actual_receipt_amount != null ? money(o.actual_receipt_amount) : '—' }}</td>
                            <td><Link class="btn btn--ghost btn--sm" :href="`/musteri/siparisler/${o.id}`">Detay →</Link></td>
                        </tr>
                        <tr v-if="!orders.data.length">
                            <td colspan="7">
                                <div class="empty" style="border:0">
                                    <div class="em">🧺</div>
                                    <h3 style="margin:8px 0 4px">Sipariş yok</h3>
                                    <p class="muted">Bu filtrede sipariş bulunmuyor.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="orders.links.length > 3" class="pager">
                <Link
                    v-for="(l, i) in orders.links"
                    :key="i"
                    :href="l.url || '#'"
                    :class="{ 'is-active': l.active }"
                    :style="!l.url ? 'opacity:.4; pointer-events:none' : ''"
                    preserve-scroll
                    v-html="l.label"
                />
            </nav>
        </div>
    </AppLayout>
</template>
