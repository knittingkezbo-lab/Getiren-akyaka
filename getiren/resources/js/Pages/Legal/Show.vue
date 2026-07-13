<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    slug: { type: String, required: true },
    doc: { type: Object, required: true },
    nav: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="doc.title" />

    <div class="legal">
        <header class="legal__top">
            <Link href="/" class="brand"><span class="brand__mark">◐</span> getiren<b>akyaka</b></Link>
        </header>

        <div class="legal__wrap">
            <aside class="legal__nav">
                <Link
                    v-for="n in nav"
                    :key="n.slug"
                    :href="`/hukuki/${n.slug}`"
                    class="legal__navitem"
                    :class="{ 'is-active': n.slug === slug }"
                >{{ n.title }}</Link>
            </aside>

            <main class="legal__main">
                <div class="legal__draft">⚠ Bu metin <b>taslaktır</b> ve hukuki onay sürecindedir. Nihai metinler yürürlüğe girince güncellenecektir.</div>
                <h1>{{ doc.title }}</h1>

                <section v-for="(b, i) in doc.blocks" :key="i" class="legal__block">
                    <h2 v-if="b.h">{{ b.h }}</h2>
                    <p v-for="(p, j) in (b.p || [])" :key="j">{{ p }}</p>
                    <ul v-if="b.list"><li v-for="(li, k) in b.list" :key="k">{{ li }}</li></ul>
                </section>

                <Link href="/" class="btn btn--ghost" style="margin-top:22px">← Ana sayfa</Link>
            </main>
        </div>
    </div>
</template>

<style scoped>
.legal { min-height: 100vh; background: var(--bg, #faf4ec); }
.legal__top { padding: 18px 24px; border-bottom: 1px solid var(--line, #eaddcd); }
.legal__wrap { max-width: 980px; margin: 0 auto; display: grid; grid-template-columns: 220px 1fr; gap: 28px; padding: 28px 24px 64px; }
.legal__nav { display: flex; flex-direction: column; gap: 2px; position: sticky; top: 24px; align-self: start; }
.legal__navitem { padding: 9px 12px; border-radius: 10px; text-decoration: none; color: var(--muted, #7a7067); font-size: 14px; font-weight: 600; }
.legal__navitem:hover { background: var(--surface-2, #f2ebe1); }
.legal__navitem.is-active { background: var(--primary-soft, #f6e3d7); color: var(--primary-2, #b8502e); }
.legal__main { background: var(--surface, #fff); border: 1px solid var(--line, #eaddcd); border-radius: 18px; padding: 30px 34px; }
.legal__draft { background: #fdf0e6; border: 1px solid #e9b98f; color: #9a5a2b; padding: 11px 14px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; }
.legal__main h1 { font-size: 26px; margin-bottom: 18px; }
.legal__block { margin-bottom: 20px; }
.legal__block h2 { font-size: 16px; margin-bottom: 6px; }
.legal__block p { color: var(--muted, #7a7067); font-size: 14.5px; line-height: 1.6; margin-bottom: 6px; }
.legal__block ul { margin: 6px 0 0 18px; color: var(--muted, #7a7067); font-size: 14.5px; line-height: 1.7; }
@media (max-width: 720px) {
    .legal__wrap { grid-template-columns: 1fr; }
    .legal__nav { position: static; flex-direction: row; flex-wrap: wrap; }
}
</style>
