<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    zones: { type: Array, default: () => [] },
    settings: { type: Object, required: true },
    priceHints: { type: Array, default: () => [] },
    company: { type: Object, default: () => ({}) },
    legalDraft: { type: Boolean, default: true },
    estimateStats: { type: Object, default: () => ({ count: 0 }) },
});

// Toplu fiyat içe aktarma — mağaza gezmeden hızlı başlangıç
const importForm = useForm({ text: '' });
const runImport = () =>
    importForm.post('/yonetici/ayarlar/fiyat-ice-aktar', {
        preserveScroll: true,
        onSuccess: () => importForm.reset('text'),
    });

// Sözlük: kaynak/eskime bilgisi props'ta, düzenlenen kopyada değil — id ile eşle
const hintMeta = (id) => props.priceHints.find((p) => p.id === id) ?? {};
const onlyReview = ref(false);
const reviewCount = computed(() => props.priceHints.filter((p) => p.needs_review).length);
const visibleHints = computed(() =>
    onlyReview.value ? form.priceHints.filter((p) => hintMeta(p.id).needs_review) : form.priceHints,
);

const money = (n) => Number(n).toLocaleString('tr-TR');

// Şirket bilgisi alanları — hukuki sayfalar + iletişim bunları kullanır
const companyFields = [
    { key: 'legal_name', label: 'İşletme unvanı', hint: 'Şahıs işletmesinde ad soyad veya ticari ad' },
    { key: 'owner', label: 'İşletme sahibi' },
    { key: 'type', label: 'İşletme türü' },
    { key: 'tax_office', label: 'Vergi dairesi' },
    { key: 'tax_no', label: 'Vergi / TC no', hint: 'PayTR onayına kadar açık; sonra boşaltarak gizleyebilirsin' },
    { key: 'mersis', label: 'MERSİS (varsa)' },
    { key: 'etbis', label: 'ETBİS (yayına girince)' },
    { key: 'nace', label: 'NACE / faaliyet' },
    { key: 'phone', label: 'Telefon' },
    { key: 'email', label: 'E-posta' },
    { key: 'kep', label: 'KEP (varsa)' },
    { key: 'website', label: 'Web adresi' },
    { key: 'hours', label: 'Hizmet saatleri', hint: 'Teslimat ve sözleşme sayfalarında görünür' },
    { key: 'address', label: 'Açık adres', full: true },
    { key: 'service_areas', label: 'Hizmet bölgeleri', full: true },
];

const buildCompany = () => Object.fromEntries(companyFields.map((f) => [f.key, props.company[f.key] ?? '']));

const form = useForm({
    zones: props.zones.map((z) => ({ id: z.id, name: z.name, service_fee: Number(z.service_fee), is_active: !!z.is_active })),
    settings: {
        safety_buffer_pct: Number(props.settings.safety_buffer_pct),
        unknown_buffer_pct: Number(props.settings.unknown_buffer_pct),
        fallback_item_price: Number(props.settings.fallback_item_price),
        min_order_total: Number(props.settings.min_order_total),
        accepting_orders: !!props.settings.accepting_orders,
        auto_assign_courier: !!props.settings.auto_assign_courier,
    },
    priceHints: props.priceHints.map((p) => ({ id: p.id, keyword: p.keyword, category: p.category, unit_price: Number(p.unit_price) })),
    company: buildCompany(),
    legal_draft: props.legalDraft,
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
                        <div class="field">
                            <label class="label">Bilinmeyen kalem payı</label>
                            <div class="input-group"><input class="input" type="number" min="0" max="200" v-model.number="form.settings.unknown_buffer_pct" /><span class="addon">%</span></div>
                            <p class="hint" style="margin-top:5px">Tanımadığımız kalem varsa pay buna yükselir.</p>
                        </div>
                        <div class="field">
                            <label class="label">Bilinmeyen kalem fiyatı</label>
                            <div class="input-group"><input class="input" type="number" min="0" v-model.number="form.settings.fallback_item_price" /><span class="addon">TL</span></div>
                            <p class="hint" style="margin-top:5px">Sözlükte olmayan kalemin varsayılan birim fiyatı.</p>
                        </div>
                    </div>
                    <div class="stack-sm" style="margin-top:4px">
                        <div class="list__row"><div><b>Siparişleri kabul et</b><p class="hint" style="margin-top:2px">Kapatınca yeni sipariş alınmaz.</p></div><label class="switch"><input type="checkbox" v-model="form.settings.accepting_orders" /><span class="track"></span></label></div>
                        <div class="list__row"><div><b>Otomatik kurye ataması</b><p class="hint" style="margin-top:2px">En yakın müsait kuryeye ata.</p></div><label class="switch"><input type="checkbox" v-model="form.settings.auto_assign_courier" /><span class="track"></span></label></div>
                    </div>
                </div>

                <!-- tahmin isabeti (ölç ve ayarla) -->
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Ölç ve ayarla</p><h2>Tahmin isabeti</h2></div></div>
                    <template v-if="estimateStats.count">
                        <div class="stats" style="grid-template-columns:1fr 1fr; gap:12px">
                            <div class="stat" :class="estimateStats.avg_deviation >= 0 ? 'stat--tint-green' : 'stat--tint-red'">
                                <div class="k">Ortalama sapma</div>
                                <div class="v">{{ estimateStats.avg_deviation > 0 ? '+' : '' }}{{ estimateStats.avg_deviation }} <small>%</small></div>
                            </div>
                            <div class="stat" :class="estimateStats.extra_rate > 15 ? 'stat--tint-red' : 'stat--tint-green'">
                                <div class="k">Ek ödeme oranı</div>
                                <div class="v">{{ estimateStats.extra_rate }} <small>%</small></div>
                            </div>
                        </div>
                        <p class="hint" style="margin-top:10px">
                            Son {{ estimateStats.count }} kapanan siparişte <b>tahmini ürün tutarı vs gerçek fiş</b>.
                            Pozitif sapma iyidir (fazlası müşteriye çözülür). <b>Ek ödeme oranı yükseliyorsa payı artır</b> —
                            az tahmin müşteriye sürtünme yaşatır.
                        </p>
                    </template>
                    <div v-else class="alert alert--info">
                        <span class="alert__ic">ℹ</span>
                        <div>Henüz kapanmış sipariş yok. Kurye gerçek fiyatları girmeye başlayınca tahmin isabeti burada görünür ve payı <b>tahminle değil veriyle</b> ayarlarsın.</div>
                    </div>
                </div>
            </div>

            <!-- şirket bilgileri -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">İşletme</p><h2>Şirket bilgileri</h2></div></div>
                <div class="alert alert--info" style="margin-bottom:16px">
                    <span class="alert__ic">ℹ</span>
                    <div>Bu bilgiler hukuki sayfalar ve İletişim sayfasında görünür. Bir alanı boş bırakırsan sayfada gizlenir (vergi no'yu daha sonra gizlemek için boşaltman yeterli).</div>
                </div>
                <div class="form-grid">
                    <div v-for="f in companyFields" :key="f.key" class="field" :class="{ full: f.full }">
                        <label class="label">{{ f.label }}</label>
                        <input class="input" v-model="form.company[f.key]" :placeholder="f.hint || ''" autocomplete="off" />
                        <p v-if="f.hint" class="hint" style="margin-top:5px">{{ f.hint }}</p>
                    </div>
                </div>
                <div class="list__row" style="margin-top:8px">
                    <div>
                        <b>Hukuki metinler taslak</b>
                        <p class="hint" style="margin-top:2px">Açıkken sayfaların üstünde "taslak" uyarısı görünür. Avukat metinleri onaylayınca kapat.</p>
                    </div>
                    <label class="switch"><input type="checkbox" v-model="form.legal_draft" /><span class="track"></span></label>
                </div>
            </div>

            <!-- toplu fiyat içe aktarma -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Hızlı başlangıç</p><h2>Toplu fiyat içe aktarma</h2></div></div>
                <div class="alert alert--info" style="margin-bottom:14px">
                    <span class="alert__ic">ℹ</span>
                    <div>
                        Her satıra bir kalem: <b>kelime; kategori; fiyat</b> (kategori isteğe bağlı).
                        <b>Kuryenin girdiği gerçek fiyatlar korunur</b> — içe aktarma onları ezmez.
                    </div>
                </div>
                <div class="field">
                    <textarea
                        class="textarea"
                        rows="6"
                        style="font-family:ui-monospace,monospace; font-size:13px"
                        placeholder="süt; Market; 78&#10;ekmek; Fırın; 20&#10;yumurta; Market; 145"
                        v-model="importForm.text"
                    ></textarea>
                    <p v-if="importForm.errors.text" class="error-text">⚠ {{ importForm.errors.text }}</p>
                </div>
                <div class="row">
                    <button class="btn btn--primary" :disabled="importForm.processing || !importForm.text.trim()" @click="runImport">
                        {{ importForm.processing ? 'Aktarılıyor…' : 'İçe aktar' }}
                    </button>
                </div>
            </div>

            <!-- tahmin sözlüğü -->
            <div class="card">
                <div class="card__head">
                    <div><p class="eyebrow">Varsayılan fiyatlar</p><h2>Tahmin sözlüğü</h2></div>
                    <label class="check" style="font-size:13px">
                        <input type="checkbox" v-model="onlyReview" />
                        <span>Sadece gözden geçirilecekler ({{ reviewCount }})</span>
                    </label>
                </div>
                <p class="muted" style="margin-bottom:14px">
                    Serbest metindeki kelimeler bu tabloyla eşleşerek tahmini oluşturur.
                    <b>Gerçek fiyat</b> etiketli olanlar sahadan öğrenildi — en güvenilir olanlar bunlar.
                </p>
                <div class="tablewrap">
                    <table class="tbl">
                        <thead><tr><th>Anahtar kelime</th><th>Kategori</th><th>Kaynak</th><th class="num" style="width:190px">Birim fiyat</th></tr></thead>
                        <tbody>
                            <tr v-for="p in visibleHints" :key="p.id">
                                <td><b>{{ p.keyword }}</b></td>
                                <td>{{ p.category }}</td>
                                <td>
                                    <span class="badge" :class="`badge--${hintMeta(p.id).tone}`">{{ hintMeta(p.id).source_label }}</span>
                                    <span v-if="hintMeta(p.id).needs_review" class="badge badge--amber" style="margin-left:5px">gözden geçir</span>
                                </td>
                                <td class="num"><div class="input-group" style="width:150px; margin-left:auto"><input class="input" type="number" min="0" v-model.number="p.unit_price" /><span class="addon">TL</span></div></td>
                            </tr>
                            <tr v-if="!visibleHints.length"><td colspan="4" class="muted" style="text-align:center; padding:20px">Gözden geçirilecek kalem yok 👌</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
