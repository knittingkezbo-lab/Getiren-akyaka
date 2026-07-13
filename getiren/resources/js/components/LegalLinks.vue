<script setup>
const links = [
    { href: '/hukuki/kullanim-sartlari', label: 'Kullanım Şartları' },
    { href: '/hukuki/kvkk', label: 'KVKK' },
    { href: '/hukuki/gizlilik', label: 'Gizlilik' },
    { href: '/hukuki/iptal-iade', label: 'İptal/İade' },
    { href: '/hukuki/yasakli-urunler', label: 'Yasaklı Ürünler' },
    { href: '/hukuki/iletisim', label: 'İletişim' },
];
</script>

<template>
    <nav class="legal-links">
        <a v-for="l in links" :key="l.href" :href="l.href" target="_blank" rel="noopener">{{ l.label }}</a>
    </nav>
</template>

<style scoped>
.legal-links { display: flex; flex-wrap: wrap; gap: 4px 14px; justify-content: center; }
.legal-links a { font-size: 12px; color: var(--muted, #7a7067); text-decoration: none; }
.legal-links a:hover { text-decoration: underline; }
</style>
