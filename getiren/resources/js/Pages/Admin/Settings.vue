<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    zones: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
    priceHints: { type: Array, default: () => [] },
});

const money = (n) => Number(n).toLocaleString('tr-TR');

const form = useForm({
    zones: props.zones.map((z) => ({ id: z.id, name: z.name, service_fee: Number(z.service_fee), is_active: !!z.is_active })),
    settings: {
        safety_buffer_pct: Number(props.settings.safety_buffer_pct),
        min_order_total: Number(props.settings.min_order_total),
        accepting_orders: !!props.settings.accepting_orders,
        auto_assign_courier: !!props.settings.auto_assign_courier,
    },
    priceHints: props.priceHints.map((p) => ({ id: p.id, keyword: p.keyword, category: p.category, unit_price: Number(p.unit_price) })),
});

const save = () => form.post('/yonetici/ayarlar', { preserveScroll: true });
</script>

<template>
    <Head title="Ayarlar" />

    <AppLayout title="Ayarlar" subtitle="Bölgeler · fiyatlandırma · genel">
        <template #actions>
            <button class="btn btn--primary btn--sm" :disabled="form.processing" @click="save">
                {{ form.processing ? 'Kaydediliyor…' : 'Tümünü kaydet' }}
            </button>
        </template>

        <div class="stack" style="gap:20px">
            <!-- bölgeler -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Bölgeler</p><h2>Teslimat bölgeleri &amp; hizmet bedeli</h2></div></div>
                <div class="stack-sm">
                    <div v-for="z in form.zones" :key="z.id" class="list__row" :style="!z.is_active ? 'opacity:.6' : ''">
                        <div class="media">
                            <span class="avatar avatar--sm" style="background:var(--primary-soft); color:var(--primary-2)">📍</span>
                            <div><b>{{ z.name }}</b><small class="muted">{{ z.is_active ? 'Aktif' : 'Pasif' }}</small></div>
                        </div>
                        <div class="row">
                            <div class="input-group" style="width:150px"><input class="input" type="number" min="0" v-model.number="z.service_fee" /><span class="addon">TL</span></div>
                            <label class="switch"><input type="checkbox" v-model="z.is_active" /><span class="track"></span></label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid cols-2" style="align-items:start">
                <!-- pay + genel -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Fiyatlandırma &amp; genel</p><h2>Kurallar</h2></div></div>
                    <div class="form-grid">
                        <div class="field"><label class="label">Güvenlik payı</label><div class="input-group"><input class="input" type="number" min="0" max="100" v-model.number="form.settings.safety_buffer_pct" /><span class="addon">%</span></div></div>
                        <div class="field"><label class="label">Min. sipariş tutarı</label><div class="input-group"><input class="input" type="number" min="0" v-model.number="form.settings.min_order_total" /><span class="addon">TL</span></div></div>
                    </div>
                    <div class="stack-sm" style="margin-top:4px">
                        <div class="list__row"><div><b>Siparişleri kabul et</b><p class="hint" style="margin-top:2px">Kapatınca yeni sipariş alınmaz.</p></div><label class="switch"><input type="checkbox" v-model="form.settings.accepting_orders" /><span class="track"></span></label></div>
                        <div class="list__row"><div><b>Otomatik kurye ataması</b><p class="hint" style="margin-top:2px">En yakın müsait kuryeye ata.</p></div><label class="switch"><input type="checkbox" v-model="form.settings.auto_assign_courier" /><span class="track"></span></label></div>
                    </div>
                </div>

                <!-- önizleme -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Önizleme</p><h2>Örnek hesap</h2></div></div>
                    <div class="alert alert--info">
                        <span class="alert__ic">ℹ</span>
                        <div>400 TL ürün → <b>{{ Math.ceil((400 * form.settings.safety_buffer_pct) / 100) }} TL</b> güvenlik payı provizyona eklenir. Asgari ürün tutarı: <b>{{ money(form.settings.min_order_total) }} TL</b>.</div>
                    </div>
                </div>
            </div>

            <!-- tahmin sözlüğü -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Varsayılan fiyatlar</p><h2>Tahmin sözlüğü</h2></div></div>
                <p class="muted" style="margin-bottom:14px">Serbest metindeki kelimeler bu tabloyla eşleşerek ön tahmini oluşturur.</p>
                <div class="tablewrap">
                    <table class="tbl">
                        <thead><tr><th>Anahtar kelime</th><th>Kategori</th><th class="num" style="width:190px">Birim fiyat</th></tr></thead>
                        <tbody>
                            <tr v-for="p in form.priceHints" :key="p.id">
                                <td><b>{{ p.keyword }}</b></td>
                                <td>{{ p.category }}</td>
                                <td class="num"><div class="input-group" style="width:150px; margin-left:auto"><input class="input" type="number" min="0" v-model.number="p.unit_price" /><span class="addon">TL</span></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
