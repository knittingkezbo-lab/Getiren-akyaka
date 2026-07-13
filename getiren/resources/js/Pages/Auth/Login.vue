<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import LegalLinks from '@/components/LegalLinks.vue';

const form = useForm({
    email: 'gencer@bizsim.com',
    password: 'password',
    remember: true,
});

const submit = () =>
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });

// Hareketsizlik nedeniyle otomatik çıkış yapıldıysa bilgilendir
const timedOut = ref(false);
onMounted(() => {
    try {
        if (localStorage.getItem('idle_logout')) {
            timedOut.value = true;
            localStorage.removeItem('idle_logout');
        }
    } catch (e) { /* yoksay */ }
});
</script>

<template>
    <Head title="Giriş" />

    <div class="authpage">
        <div class="authcard">
            <!-- sol: marka -->
            <div class="authside">
                <span class="brand"><span class="brand__mark">◐</span> getiren<b>akyaka</b></span>
                <h2>Gökova’nın<br />getir-götür ustası.</h2>
                <p>Ne lazımsa yaz; tutarı bloke edelim, gerçek fişe göre keselim, fazlasını iade edelim.</p>
                <ul class="authpoints">
                    <li><span class="tick">✓</span> Şeffaf bloke &amp; iade</li>
                    <li><span class="tick">✓</span> Akyaka · Gökova · Akçapınar</li>
                    <li><span class="tick">✓</span> Kuryeni adım adım izle</li>
                </ul>
            </div>

            <!-- sağ: form -->
            <div class="authbody">
                <p class="eyebrow">Tekrar hoş geldin</p>
                <h2 style="font-size:26px; margin:6px 0 4px">Giriş yap</h2>
                <p class="muted" style="margin-bottom:20px">Hesabınla devam et.</p>

                <p v-if="timedOut" style="background:var(--primary-soft); color:var(--primary-2); padding:11px 14px; border-radius:12px; font-weight:600; font-size:13.5px; margin-bottom:18px">
                    Oturumun hareketsizlik nedeniyle güvenlik için kapatıldı — lütfen tekrar giriş yap.
                </p>

                <form @submit.prevent="submit">
                    <div class="field" :class="{ 'field--error': form.errors.email }">
                        <label class="label" for="email">E-posta</label>
                        <div class="input-icon">
                            <span class="ic">✉</span>
                            <input id="email" v-model="form.email" type="email" class="input"
                                   placeholder="ornek@eposta.com" autofocus />
                        </div>
                        <p v-if="form.errors.email" class="error-text">⚠ {{ form.errors.email }}</p>
                    </div>

                    <div class="field">
                        <div class="spread" style="margin-bottom:0">
                            <label class="label" for="password" style="margin-bottom:0">Şifre</label>
                            <a href="#" class="hint" style="font-weight:700; color:var(--primary-2)">Şifremi unuttum</a>
                        </div>
                        <div class="input-icon" style="margin-top:7px">
                            <span class="ic">🔒</span>
                            <input id="password" v-model="form.password" type="password" class="input"
                                   placeholder="••••••••" />
                        </div>
                    </div>

                    <label class="check" style="margin:4px 0 20px">
                        <input type="checkbox" v-model="form.remember" /> Beni hatırla
                    </label>

                    <button type="submit" class="btn btn--primary btn--block btn--lg" :disabled="form.processing">
                        {{ form.processing ? 'Giriş yapılıyor…' : 'Giriş yap' }}
                    </button>

                    <p class="center muted" style="margin-top:22px">
                        Hesabın yok mu?
                        <Link href="/register" style="font-weight:800; color:var(--primary-2)">Kayıt ol</Link>
                    </p>
                </form>

                <LegalLinks style="margin-top:22px" />
            </div>
        </div>
    </div>
</template>
