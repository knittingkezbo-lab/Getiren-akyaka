<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { reactive } from 'vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    zones: { type: Array, default: () => [] },
    couriers: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
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

const filters = reactive({
    status: props.filters.status ?? '',
    zone: props.filters.zone ?? '',
    q: props.filters.q ?? '',
});
const applyFilters = () =>
    router.get('/yonetici/siparisler', filters, { preserveState: true, preserveScroll: true, replace: true });

const assign = (orderId, e) => {
    const courierId = e.target.value;
    if (courierId) router.post(`/yonetici/siparisler/${orderId}/ata`, { courier_id: courierId }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Siparişler" />

    <AppLayout title="Siparişler" subtitle="Tüm siparişler · atama ve durum yönetimi">
        <div class="stack">
            <!-- filtreler -->
            <div class="card card--pad-sm">
                <div class="toolbar">
                    <div class="field" style="margin:0; flex:1; min-width:220px">
                        <label class="label">Ara</label>
                        <div class="input-icon"><span class="ic">🔍</span><input class="input" placeholder="Sipariş no / içerik" v-model="filters.q" @keyup.enter="applyFilters" /></div>
                    </div>
                    <div class="field" style="margin:0; min-width:160px">
                        <label class="label">Durum</label>
                        <select class="select" v-model="filters.status" @change="applyFilters">
                            <option value="">Tüm durumlar</option>
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div class="field" style="margin:0; min-width:150px">
                        <label class="label">Bölge</label>
                        <select class="select" v-model="filters.zone" @change="applyFilters">
                            <option value="">Tüm bölgeler</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>
                    <button class="btn btn--primary btn--sm" @click="applyFilters">Uygula</button>
                </div>
            </div>

            <!-- tablo -->
            <div class="tablewrap">
                <table class="tbl">
                    <thead>
                        <tr><th>#</th><th>Müşteri</th><th>İçerik</th><th>Bölge</th><th>Durum</th><th class="num">Provizyon</th><th class="num">Fiş</th><th>Kurye</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in orders.data" :key="o.id">
                            <td><b>{{ o.code }}</b></td>
                            <td>{{ o.customer_name }}</td>
                            <td>{{ o.raw_text }}</td>
                            <td>{{ o.zone_name }}</td>
                            <td><span class="badge" :class="badgeClass(o.status)">{{ o.status_label }}</span></td>
                            <td class="num">{{ money(o.reserved_amount) }}</td>
                            <td class="num">{{ o.actual_receipt_amount != null ? money(o.actual_receipt_amount) : '—' }}</td>
                            <td>
                                <select v-if="o.can_assign" class="select" style="width:150px; padding:8px 30px 8px 12px" @change="assign(o.id, $event)">
                                    <option value="">Kurye ata…</option>
                                    <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <span v-else class="muted">{{ o.courier_name ?? '—' }}</span>
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length"><td colspan="8" class="muted" style="text-align:center; padding:24px">Kayıt bulunamadı.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- sayfalama -->
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
