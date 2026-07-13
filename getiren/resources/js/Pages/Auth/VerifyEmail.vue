<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps({
    email: { type: String, default: '' },
    status: { type: String, default: null },
});

const resendForm = useForm({});
const resend = () => resendForm.post('/email/dogrulama-gonder', { preserveScroll: true });
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="E-posta doğrulama" />

    <div class="authpage">
        <div class="authcard">
            <!-- sol: marka -->
            <div class="authside">
                <span class="brand"><span class="brand__mark">◐</span> getiren<b>akyaka</b></span>
                <h2>Son bir adım<br />kaldı.</h2>
                <p>Hesabını güvende tutmak için e-posta adresini doğruluyoruz.</p>
                <ul class="authpoints">
                    <li><span class="tick">✓</span> Gelen kutunu aç</li>
                    <li><span class="tick">✓</span> Doğrulama linkine tıkla</li>
                    <li><span class="tick">✓</span> Sipariş vermeye başla</li>
                </ul>
            </div>

            <!-- sağ: içerik -->
            <div class="authbody">
                <p class="eyebrow">Neredeyse hazırsın</p>
                <h2 style="font-size:26px; margin:6px 0 4px">E-postanı doğrula</h2>
                <p class="muted" style="margin-bottom:18px">
                    <b>{{ email }}</b> adresine bir doğrulama linki gönderdik. Gelen kutunu açıp linke tıkla.
                </p>

                <p v-if="status" style="background:var(--primary-soft); color:var(--primary-2); padding:11px 14px; border-radius:12px; font-weight:600; font-size:14px; margin-bottom:16px">
                    ✓ {{ status }}
                </p>

                <button class="btn btn--primary btn--block btn--lg" :disabled="resendForm.processing" @click="resend">
                    {{ resendForm.processing ? 'Gönderiliyor…' : 'Linki tekrar gönder' }}
                </button>

                <p class="center muted" style="margin-top:22px">
                    Yanlış hesap mı?
                    <a href="#" style="font-weight:800; color:var(--primary-2)" @click.prevent="logout">Çıkış yap</a>
                </p>
            </div>
        </div>
    </div>
</template>
