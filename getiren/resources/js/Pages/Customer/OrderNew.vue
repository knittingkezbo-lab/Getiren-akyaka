<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    zones: { type: Array, default: () => [] },
    priceHints: { type: Array, default: () => [] },
    bufferPct: { type: Number, default: 15 },
    minOrderTotal: { type: Number, default: 0 },
    addresses: { type: Array, default: () => [] },
});

const form = useForm({
    raw_text: '1 kutu süt, 2 ağrı kesici, ekmek',
    zone_id: props.zones[0]?.id ?? null,
    address_label: props.addresses[0]?.label ?? 'Ev',
    address_text: props.addresses[0]?.line ?? '',
    customer_note: '',
    terms_accepted: false,
});

const money = (n) => Number(n).toLocaleString('tr-TR');
const selectedZone = computed(() => props.zones.find((z) => z.id === form.zone_id) ?? props.zones[0]);

// İstemci önizlemesi — sunucudaki OrderEstimator ile AYNI algoritma (sunucu otoriter)
const estimate = computed(() => {
    const parts = (form.raw_text || '')
        .toLocaleLowerCase('tr')
        .split(/[,\n;]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    if (!parts.length || !selectedZone.value) return null;

    const hints = [...props.priceHints].sort((a, b) => b.keyword.length - a.keyword.length);
    let items = 0;
    for (const part of parts) {
        const m = part.match(/^(\d+)/);
        const qty = m ? Math.max(1, parseInt(m[1], 10)) : 1;
        let price = 40;
        for (const h of hints) {
            const kw = h.keyword.toLocaleLowerCase('tr').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            if (new RegExp('(^|[^\\p{L}])' + kw + '([^\\p{L}]|$)', 'u').test(part)) {
                price = Number(h.unit_price);
                break;
            }
        }
        items += qty * price;
    }
    items = Math.max(items, Number(props.minOrderTotal));
    const buffer = Math.ceil((items * Number(props.bufferPct)) / 100);
    const fee = Number(selectedZone.value.service_fee);
    return { items, buffer, fee, total: items + buffer + fee };
});

const canSubmit = computed(() => !!estimate.value);

const quickItems = ['süt', 'ekmek', 'su', 'kahve', 'ağrı kesici', 'gazete'];
const addQuick = (w) => {
    form.raw_text = form.raw_text.trim() ? `${form.raw_text.trim()}, ${w}` : w;
};
const pickAddress = (e) => {
    const a = props.addresses.find((x) => String(x.id) === e.target.value);
    if (a) {
        form.address_label = a.label;
        form.address_text = a.line;
    }
};

const submit = () => form.post('/musteri/siparis');

// "Ne lazım?" otomatik öneri — son segmentteki (virgülden sonraki) ürünü sözlükten tamamlar
const sugOpen = ref(false);
const onSugBlur = () => setTimeout(() => (sugOpen.value = false), 120);

const segInfo = computed(() => {
    const text = form.raw_text || '';
    const start = Math.max(text.lastIndexOf(','), text.lastIndexOf('\n'), text.lastIndexOf(';')) + 1;
    const seg = text.slice(start);
    const m = seg.match(/^(\s*\d+\s*(?:kutu|adet|paket|kg|gr|gram|şişe|litre|lt|ml|dilim|top)?\s*)?(.*)$/i);
    return { start, prefix: m?.[1] ?? '', query: (m?.[2] ?? '').trim() };
});

const suggestions = computed(() => {
    const q = segInfo.value.query.toLocaleLowerCase('tr');
    if (q.length < 2) return [];
    return props.priceHints
        .map((h) => h.keyword)
        .filter((k) => {
            const kl = k.toLocaleLowerCase('tr');
            return kl !== q && kl.includes(q);
        })
        .sort((a, b) => {
            const sa = a.toLocaleLowerCase('tr').startsWith(q) ? 0 : 1;
            const sb = b.toLocaleLowerCase('tr').startsWith(q) ? 0 : 1;
            return sa - sb || a.length - b.length;
        })
        .slice(0, 6);
});

const pickSuggestion = (kw) => {
    const { start, prefix } = segInfo.value;
    form.raw_text = form.raw_text.slice(0, start) + prefix + kw + ', ';
    sugOpen.value = false;
};
</script>

<template>
    <Head title="Yeni sipariş" />

    <AppLayout title="Yeni sipariş" subtitle="Üç adımda hazır — acele etme, biz buradayız.">

        <div class="grid col-wide" style="align-items:start">
            <!-- FORM -->
            <div class="card">
                <!-- 1 -->
                <div class="formsection">
                    <div class="formsection__head">
                        <span class="formsection__num">1</span>
                        <div><h3>Ne lazım?</h3><p>Aklından ne geçiyorsa yaz — market, eczane, fırın fark etmez.</p></div>
                    </div>
                    <div class="sug-wrap">
                        <textarea v-model="form.raw_text" class="textarea" placeholder="Örn: 1 kutu süt, 2 ağrı kesici, ekmek" @focus="sugOpen = true" @blur="onSugBlur"></textarea>
                        <ul v-if="sugOpen && suggestions.length" class="sug">
                            <li v-for="s in suggestions" :key="s" @mousedown.prevent="pickSuggestion(s)">＋ {{ s }}</li>
                        </ul>
                    </div>
                    <p v-if="form.errors.raw_text" class="error-text">⚠ {{ form.errors.raw_text }}</p>
                    <div class="quick">
                        <button v-for="w in quickItems" :key="w" type="button" class="q" @click="addQuick(w)">＋ {{ w }}</button>
                    </div>
                    <p class="banned-note">⚠ Reçeteli ilaç, alkol, tütün, yaş sınırlı veya hukuka aykırı ürünler temin edilmez.</p>
                </div>

                <!-- 2 -->
                <div class="formsection">
                    <div class="formsection__head">
                        <span class="formsection__num">2</span>
                        <div><h3>Nereye getirelim?</h3><p>Teslimat bölgesi ve adresini seç.</p></div>
                    </div>
                    <label class="label">Bölge</label>
                    <div class="pick" style="margin-bottom:16px">
                        <template v-for="z in zones" :key="z.id">
                            <input :id="'z' + z.id" type="radio" :value="z.id" v-model="form.zone_id" />
                            <label :for="'z' + z.id"><b>{{ z.name }}</b><span>{{ money(z.service_fee) }} TL</span></label>
                        </template>
                    </div>
                    <div class="field" style="margin-bottom:0" v-if="addresses.length">
                        <label class="label">Adres</label>
                        <select class="select" @change="pickAddress">
                            <option v-for="a in addresses" :key="a.id" :value="a.id">{{ a.label }} · {{ a.line }}</option>
                        </select>
                    </div>
                </div>

                <!-- 3 -->
                <div class="formsection">
                    <div class="formsection__head">
                        <span class="formsection__num">3</span>
                        <div><h3>Kuryeye not</h3><p>Gerekirse sana nasıl ulaşsın?</p></div>
                    </div>
                    <input v-model="form.customer_note" class="input" placeholder="Zil çalışmıyor, arayın lütfen" />
                </div>
            </div>

            <!-- ÖZET (yapışkan) -->
            <div class="order-summary">
                <div class="ticket">
                    <div class="spread">
                        <div><p class="eyebrow">Tahmini fiş</p><h3 style="margin-top:2px">Ön hesap</h3></div>
                        <span class="badge badge--amber">%{{ bufferPct }} pay dahil</span>
                    </div>
                    <hr class="ticket__perf" />

                    <template v-if="estimate">
                        <div class="ticket__row"><span>Ürün tahmini</span><b>{{ money(estimate.items) }} TL</b></div>
                        <div class="ticket__row"><span>%{{ bufferPct }} güvenlik payı</span><b>{{ money(estimate.buffer) }} TL</b></div>
                        <div class="ticket__row"><span>Teslimat · {{ selectedZone?.name }}</span><b>{{ money(estimate.fee) }} TL</b></div>
                        <hr class="ticket__perf" />
                        <div class="ticket__row ticket__row--total"><span>Provizyona alınacak</span><b>{{ money(estimate.total) }} TL</b></div>
                    </template>
                    <p v-else class="muted" style="padding:10px 0">Sipariş metnini yaz, tahmin çıksın.</p>

                    <div class="alert alert--info" style="margin-top:14px">
                        <span class="alert__ic">ℹ</span>
                        <div>Getiren Akyaka ürün satıcısı değildir; ürünleri senin adına temin eder. Tutar <b>tahminidir</b>, gerçek fişe göre kesinleşir; fazlası iade edilir.</div>
                    </div>

                    <label class="terms-check" :class="{ 'terms-check--error': form.errors.terms_accepted }">
                        <input type="checkbox" v-model="form.terms_accepted" />
                        <span>
                            <a href="/hukuki/kullanim-sartlari" target="_blank" rel="noopener">Ön bilgilendirme ve kullanım şartlarını</a>
                            okudum; ürün bedelinin tahmini olduğunu, gerçek fişe göre kesinleşeceğini ve siparişin ödeme yükümlülüğü doğurduğunu kabul ediyorum.
                        </span>
                    </label>
                    <p v-if="form.errors.terms_accepted" class="error-text">⚠ {{ form.errors.terms_accepted }}</p>

                    <button
                        class="btn btn--primary btn--block btn--lg"
                        style="margin-top:12px"
                        :disabled="!canSubmit || !form.terms_accepted || form.processing"
                        @click="submit"
                    >
                        {{ form.processing ? 'Provizyona alınıyor…' : estimate ? `Onayla ve ${money(estimate.total)} TL provizyona al` : 'Onayla ve provizyona al' }}
                    </button>
                    <Link href="/musteri" class="btn btn--ghost btn--block" style="margin-top:8px">Vazgeç</Link>
                </div>
            </div>
        </div>

        <!-- mobil alt onay barı -->
        <div class="stickybar" v-if="estimate">
            <div><small class="muted">Provizyona alınacak</small><br /><b>{{ money(estimate.total) }} TL</b></div>
            <button class="btn btn--primary" :disabled="!canSubmit || !form.terms_accepted || form.processing" @click="submit">Onayla →</button>
        </div>
    </AppLayout>
</template>

<style scoped>
.banned-note { margin-top: 10px; font-size: 12.5px; color: var(--muted); }
.sug-wrap { position: relative; }
.sug { position: absolute; left: 0; right: 0; top: 100%; margin-top: 4px; list-style: none; padding: 4px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; box-shadow: var(--shadow); z-index: 20; max-height: 220px; overflow-y: auto; }
.sug li { padding: 8px 12px; border-radius: 8px; cursor: pointer; font-size: 14px; }
.sug li:hover { background: var(--primary-soft); color: var(--primary-2); }
.terms-check { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; font-size: 12.5px; color: var(--muted); cursor: pointer; line-height: 1.45; }
.terms-check input { margin-top: 2px; flex-shrink: 0; }
.terms-check a { color: var(--primary-2); font-weight: 700; }
.terms-check--error { color: #c0392b; }
</style>
