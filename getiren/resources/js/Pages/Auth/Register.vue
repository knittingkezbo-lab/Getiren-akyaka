<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import LegalLinks from '@/components/LegalLinks.vue';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    role: 'customer',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () =>
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Kayıt ol" />

    <div class="authpage">
        <div class="authcard">
            <!-- sol: marka -->
            <div class="authside">
                <span class="brand"><span class="brand__mark">◐</span> getiren<b>akyaka</b></span>
                <h2>Dakikalar içinde<br />ilk siparişin hazır.</h2>
                <p>Ne lazımsa yaz; tutarı provizyona alalım, gerçek fişe göre keselim, fazlasını iade edelim.</p>
                <ul class="authpoints">
                    <li><span class="tick">1</span> Hesabını oluştur</li>
                    <li><span class="tick">2</span> Ne lazımsa yaz</li>
                    <li><span class="tick">3</span> Onayla, kapına gelsin</li>
                </ul>
            </div>

            <!-- sağ: form -->
            <div class="authbody">
                <p class="eyebrow">Yeni hesap</p>
                <h2 style="font-size:26px; margin:6px 0 4px">Kayıt ol</h2>
                <p class="muted" style="margin-bottom:20px">Bilgilerini gir, hemen başlayalım.</p>

                <form @submit.prevent="submit">
                    <div class="form-grid">
                        <div class="field" :class="{ 'field--error': form.errors.first_name }">
                            <label class="label">Ad <span class="req">*</span></label>
                            <input v-model="form.first_name" class="input" type="text" placeholder="Gencer" autofocus />
                            <p v-if="form.errors.first_name" class="error-text">⚠ {{ form.errors.first_name }}</p>
                        </div>
                        <div class="field" :class="{ 'field--error': form.errors.last_name }">
                            <label class="label">Soyad <span class="req">*</span></label>
                            <input v-model="form.last_name" class="input" type="text" placeholder="Ger" />
                            <p v-if="form.errors.last_name" class="error-text">⚠ {{ form.errors.last_name }}</p>
                        </div>
                    </div>

                    <div class="field" :class="{ 'field--error': form.errors.email }">
                        <label class="label">E-posta <span class="req">*</span></label>
                        <input v-model="form.email" class="input" type="email" placeholder="ornek@eposta.com" />
                        <p v-if="form.errors.email" class="error-text">⚠ {{ form.errors.email }}</p>
                    </div>

                    <div class="field" :class="{ 'field--error': form.errors.phone }">
                        <label class="label">Telefon</label>
                        <div class="input-group">
                            <span class="addon">+90</span>
                            <input v-model="form.phone" class="input" type="tel" placeholder="5xx xxx xx xx" />
                        </div>
                        <p v-if="form.errors.phone" class="error-text">⚠ {{ form.errors.phone }}</p>
                    </div>

                    <div class="field">
                        <label class="label">Hesap türü</label>
                        <div class="radio-row">
                            <label class="radio-card" :style="form.role === 'customer' ? 'border-color:var(--primary); background:var(--primary-soft)' : ''">
                                <input type="radio" value="customer" v-model="form.role" /> 🛒 Müşteri
                            </label>
                            <label class="radio-card" :style="form.role === 'courier' ? 'border-color:var(--primary); background:var(--primary-soft)' : ''">
                                <input type="radio" value="courier" v-model="form.role" /> 🛵 Kurye
                            </label>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field" :class="{ 'field--error': form.errors.password }">
                            <label class="label">Şifre <span class="req">*</span></label>
                            <input v-model="form.password" class="input" type="password" placeholder="En az 8 karakter" />
                            <p v-if="form.errors.password" class="error-text">⚠ {{ form.errors.password }}</p>
                        </div>
                        <div class="field">
                            <label class="label">Şifre tekrar <span class="req">*</span></label>
                            <input v-model="form.password_confirmation" class="input" type="password" placeholder="••••••••" />
                        </div>
                    </div>

                    <div class="field" :class="{ 'field--error': form.errors.terms }" style="margin-bottom:12px">
                        <label class="check">
                            <input type="checkbox" v-model="form.terms" />
                            <span>
                                <a href="/hukuki/kullanim-sartlari" target="_blank" rel="noopener" style="color:var(--primary-2); font-weight:700">Kullanım koşulları</a>
                                ve
                                <a href="/hukuki/kvkk" target="_blank" rel="noopener" style="color:var(--primary-2); font-weight:700">KVKK aydınlatma metni</a>ni
                                okudum, onaylıyorum.
                            </span>
                        </label>
                        <p v-if="form.errors.terms" class="error-text">⚠ {{ form.errors.terms }}</p>
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg" :disabled="form.processing">
                        {{ form.processing ? 'Oluşturuluyor…' : 'Hesabı oluştur' }}
                    </button>

                    <p class="center muted" style="margin-top:20px">
                        Zaten üye misin?
                        <Link href="/login" style="font-weight:800; color:var(--primary-2)">Giriş yap</Link>
                    </p>
                </form>

                <LegalLinks style="margin-top:18px" />
            </div>
        </div>
    </div>
</template>
