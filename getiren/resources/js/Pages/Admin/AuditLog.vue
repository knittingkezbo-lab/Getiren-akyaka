<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { reactive, ref } from 'vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
});

const filters = reactive({ q: props.filters.q ?? '', action: props.filters.action ?? '' });
const applyFilters = () =>
    router.get('/yonetici/denetim', filters, { preserveState: true, preserveScroll: true, replace: true });

const opened = ref(new Set());
const toggle = (id) => {
    const next = new Set(opened.value);
    next.has(id) ? next.delete(id) : next.add(id);
    opened.value = next;
};

const fmt = (v) => {
    if (v === true) return 'açık';
    if (v === false) return 'kapalı';
    if (v === null || v === undefined || v === '') return '—';
    return String(v);
};

// meta iki biçimde gelir: ayar değişiklikleri { changes: { etiket: {eski, yeni} } }, diğerleri düz { anahtar: değer }
const metaRows = (meta) =>
    Object.entries(meta ?? {}).flatMap(([key, value]) =>
        key === 'changes'
            ? Object.entries(value).map(([label, pair]) => ({ label, text: `${fmt(pair.eski)} → ${fmt(pair.yeni)}` }))
            : [{ label: key, text: fmt(value) }],
    );
</script>

<template>
    <Head title="Denetim kaydı" />

    <AppLayout title="Denetim kaydı" subtitle="Yönetici eylemleri · değiştirilemez kayıt">
        <div class="stack">
            <!-- filtreler -->
            <div class="card card--pad-sm">
                <div class="toolbar">
                    <div class="field" style="margin:0; flex:1; min-width:220px">
                        <label class="label">Ara</label>
                        <div class="input-icon">
                            <span class="ic">🔍</span>
                            <input class="input" placeholder="Yönetici / hedef / açıklama" v-model="filters.q" @keyup.enter="applyFilters" />
                        </div>
                    </div>
                    <div class="field" style="margin:0; min-width:190px">
                        <label class="label">Eylem</label>
                        <select class="select" v-model="filters.action" @change="applyFilters">
                            <option value="">Tüm eylemler</option>
                            <option v-for="a in actions" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                    </div>
                    <button class="btn btn--primary btn--sm" @click="applyFilters">Uygula</button>
                </div>
            </div>

            <!-- kayıtlar -->
            <div class="tablewrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Zaman</th><th>Eylem</th><th>Yönetici</th><th>Hedef</th><th>Açıklama</th><th>IP</th></tr>
                    </thead>
                    <tbody>
                        <template v-for="l in logs.data" :key="l.id">
                            <tr>
                                <td><b>{{ l.at }}</b><small class="muted sub">{{ l.ago }}</small></td>
                                <td><span class="badge" :class="`badge--${l.tone}`">{{ l.action_label }}</span></td>
                                <td>{{ l.actor_name }}</td>
                                <td>{{ l.subject_label ?? '—' }}</td>
                                <td>
                                    {{ l.description }}
                                    <button v-if="metaRows(l.meta).length" class="linkbtn" @click="toggle(l.id)">
                                        {{ opened.has(l.id) ? 'detayı gizle' : 'detay' }}
                                    </button>
                                </td>
                                <td class="muted">{{ l.ip ?? '—' }}</td>
                            </tr>
                            <tr v-if="opened.has(l.id)" class="detail">
                                <td colspan="6">
                                    <div v-for="(m, i) in metaRows(l.meta)" :key="i" class="detail__row">
                                        <span class="muted">{{ m.label }}</span>
                                        <b>{{ m.text }}</b>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="muted" style="text-align:center; padding:24px">Henüz denetim kaydı yok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- sayfalama -->
            <nav v-if="logs.links.length > 3" class="pager">
                <Link
                    v-for="(l, i) in logs.links"
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

<style scoped>
.sub { display: block; font-weight: 400; font-size: 12px; margin-top: 1px; }

.linkbtn { background: none; border: 0; padding: 0; margin-left: 8px; color: var(--primary); font: inherit; font-size: 12.5px; font-weight: 700; cursor: pointer; }
.linkbtn:hover { text-decoration: underline; }

.detail td { background: var(--surface-2); }
.detail__row { display: flex; align-items: baseline; justify-content: space-between; gap: 20px; max-width: 620px; padding: 7px 0; font-size: 13.5px; }
.detail__row + .detail__row { border-top: 1px dashed var(--line); }
</style>
