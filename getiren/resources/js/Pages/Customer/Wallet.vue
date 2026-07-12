<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    balance: { type: Number, default: 0 },
    reserved: { type: Number, default: 0 },
    transactions: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [250, 500, 1000] },
});

const money = (n) => Number(n).toLocaleString('tr-TR');
const form = useForm({ amount: 500 });
const topup = () => form.post('/musteri/cuzdan/yukle');

const icon = (type) =>
    ({ topup: '＋', hold: '🔒', release: '↩', capture: '🧾', refund: '↩', extra_charge: '＋', adjustment: '⚙' })[type] ?? '•';
const iconStyle = (t) =>
    t.is_positive ? 'background:var(--sage-soft); color:#3f5c42' : 'background:var(--danger-soft); color:var(--danger)';
</script>

<template>
    <Head title="Cüzdan" />

    <AppLayout title="Cüzdan" subtitle="Bakiye, bloke ve hareketler">
        <div class="grid col-wide" style="align-items:start">
            <!-- sol: bakiye + yükleme -->
            <div class="stack">
                <div class="card" style="background:linear-gradient(150deg,#2b2320,#4a3a34); color:#fff; border:0">
                    <p class="eyebrow" style="color:#ffcf9e">Kullanılabilir bakiye</p>
                    <div class="balance" style="color:#fff; margin:8px 0 4px">{{ money(balance) }} <small style="color:#e7d5c6">TL</small></div>
                    <div class="row" style="gap:20px; margin-top:10px">
                        <span style="color:#e7d5c6">🔒 Bloke: <b style="color:#fff">{{ money(reserved) }} TL</b></span>
                        <span style="color:#e7d5c6">Σ Toplam: <b style="color:#fff">{{ money(balance + reserved) }} TL</b></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Bakiye yükle</p><h2>Ne kadar yükleyelim?</h2></div></div>
                    <div class="pick" style="grid-template-columns:repeat(3,1fr)">
                        <template v-for="p in presets" :key="p">
                            <input :id="'p' + p" type="radio" :value="p" v-model.number="form.amount" />
                            <label :for="'p' + p"><b>{{ money(p) }} TL</b></label>
                        </template>
                    </div>
                    <div class="field" style="margin-top:16px">
                        <label class="label">Tutar</label>
                        <div class="input-group"><input class="input" type="number" v-model.number="form.amount" /><span class="addon">TL</span></div>
                        <p v-if="form.errors.amount" class="error-text">⚠ {{ form.errors.amount }}</p>
                    </div>
                    <div class="alert alert--info">
                        <span class="alert__ic">🔒</span>
                        <div>Gerçekte ödeme sağlayıcısına yönlendirilirsin; kart bilgin bizde tutulmaz. (demo: anında yüklenir)</div>
                    </div>
                    <button class="btn btn--primary btn--block btn--lg" style="margin-top:14px" :disabled="form.processing" @click="topup">
                        {{ form.processing ? 'Yükleniyor…' : `${money(form.amount || 0)} TL yükle` }}
                    </button>
                </div>
            </div>

            <!-- sağ: hareketler (ledger) -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Hesap hareketleri</p><h2>Son işlemler</h2></div></div>

                <div v-if="transactions.length" class="stack-sm">
                    <div v-for="t in transactions" :key="t.id" class="list__row">
                        <div class="media">
                            <span class="avatar avatar--sm" :style="iconStyle(t)">{{ icon(t.type) }}</span>
                            <div>
                                <b>{{ t.type_label }}<span v-if="t.order_code"> · #{{ t.order_code }}</span></b>
                                <small class="muted">{{ t.created_at }}</small>
                            </div>
                        </div>
                        <b class="num" :style="t.is_positive ? 'color:#3f7a4a' : ''">
                            {{ t.is_positive ? '+' : '' }}{{ money(t.figure) }} TL
                        </b>
                    </div>
                </div>
                <p v-else class="muted" style="padding:12px 2px">Henüz hareket yok.</p>
            </div>
        </div>
    </AppLayout>
</template>
