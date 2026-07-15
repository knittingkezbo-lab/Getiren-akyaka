<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    authorizations: { type: Object, required: true },
    summary: { type: Object, required: true },
});

const money = (n) => Number(n).toLocaleString('tr-TR');
</script>

<template>
    <Head title="Ödemelerim" />

    <AppLayout title="Ödemelerim" subtitle="Provizyon geçmişin · Getiren bakiye tutmaz">
        <div class="stack">
            <div class="alert alert--info">
                <span class="alert__ic">ℹ</span>
                <div>
                    Getiren'de <b>bakiye yoktur</b>. Her sipariş için tahmini tutar ödeme aracında
                    <b>provizyona</b> alınır; gerçek fiş gelince yalnızca fiş kadarı kesilir, kalan
                    kısım sana geri bırakılır.
                </div>
            </div>

            <div class="stats" style="grid-template-columns:repeat(3,1fr)">
                <div class="stat stat--tint-orange"><div class="k">🔒 Açık provizyon</div><div class="v">{{ money(summary.open) }} <small>TL</small></div></div>
                <div class="stat stat--tint-red"><div class="k">💳 Toplam tahsil</div><div class="v">{{ money(summary.captured) }} <small>TL</small></div></div>
                <div class="stat stat--tint-green"><div class="k">↩︎ Sana bırakılan</div><div class="v">{{ money(summary.released) }} <small>TL</small></div></div>
            </div>

            <div class="tablewrap">
                <table class="tbl">
                    <thead>
                        <tr><th>Tarih</th><th>Sipariş</th><th>Durum</th><th class="num">Provizyon</th><th class="num">Kesilen</th><th class="num">Geri bırakılan</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in authorizations.data" :key="a.id">
                            <td><b>{{ a.at }}</b><small class="muted sub">{{ a.note }}</small></td>
                            <td>
                                <Link v-if="a.order_id" :href="`/musteri/siparisler/${a.order_id}`" class="olink">{{ a.order_code }}</Link>
                                <small class="muted sub">{{ a.order_text }}</small>
                            </td>
                            <td><span class="badge" :class="`badge--${a.tone}`">{{ a.status_label }}</span></td>
                            <td class="num">{{ money(a.amount) }} TL</td>
                            <td class="num">{{ a.captured_amount != null ? money(a.captured_amount) + ' TL' : '—' }}</td>
                            <td class="num">{{ a.released_amount > 0 ? money(a.released_amount) + ' TL' : '—' }}</td>
                        </tr>
                        <tr v-if="!authorizations.data.length">
                            <td colspan="6" class="muted" style="text-align:center; padding:24px">Henüz bir ödemen yok.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="authorizations.links.length > 3" class="pager">
                <Link
                    v-for="(l, i) in authorizations.links"
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
.sub { display: block; font-weight: 400; font-size: 12px; margin-top: 1px; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.olink { font-weight: 800; color: var(--primary); text-decoration: none; }
.olink:hover { text-decoration: underline; }
</style>
