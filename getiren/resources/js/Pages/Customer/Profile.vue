<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    address: { type: Object, default: null },
    zones: { type: Array, default: () => [] },
    notifications: { type: Object, default: () => ({ notify_email: true, notify_web: true }) },
    bank: { type: Object, default: () => ({ iban: null, iban_holder: null }) },
});

const user = computed(() => usePage().props.auth?.user);
const initials = computed(() =>
    (props.profile.name || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase(),
);

const infoForm = useForm({ name: props.profile.name, email: props.profile.email, phone: props.profile.phone ?? '' });
const saveInfo = () => infoForm.put('/musteri/profil', { preserveScroll: true });

const addressForm = useForm({
    label: props.address?.label ?? 'Ev',
    line: props.address?.line ?? '',
    zone_id: props.address?.zone_id ?? props.zones[0]?.id ?? null,
});
const saveAddress = () => addressForm.put('/musteri/profil/adres', { preserveScroll: true });

const bankForm = useForm({ iban: props.bank.iban ?? '', iban_holder: props.bank.iban_holder ?? '' });
const saveBank = () => bankForm.put('/musteri/profil/banka', { preserveScroll: true });
// IBAN'ı okunurluk için 4'lü grupla (blur'da)
const formatIban = () => {
    const raw = (bankForm.iban || '').replace(/\s+/g, '').toUpperCase();
    bankForm.iban = raw.replace(/(.{4})/g, '$1 ').trim();
};

const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () =>
    passwordForm.put('/musteri/profil/sifre', { preserveScroll: true, onSuccess: () => passwordForm.reset() });

const eventList = [
    { key: 'assigned', label: 'Kurye atandı', hint: 'Siparişini bir kurye üstlendiğinde' },
    { key: 'on_the_way', label: 'Sipariş yolda', hint: 'Kurye yola çıktığında' },
    { key: 'delivered', label: 'Teslim edildi', hint: 'Sipariş tamamlandığında' },
    { key: 'extra', label: 'Ek ödeme gerekli', hint: 'Fiş bloke tutarını aştığında' },
];

const notifyForm = useForm({
    notify_email: props.notifications.notify_email,
    notify_web: props.notifications.notify_web,
    events: { ...props.notifications.events },
});
const saveNotifications = () => notifyForm.put('/musteri/profil/bildirimler', { preserveScroll: true });
</script>

<template>
    <Head title="Profil" />

    <AppLayout title="Profil" subtitle="Hesap · kişisel bilgiler ve tercihler">
        <div class="stack" style="gap:20px">
            <!-- header -->
            <div class="card">
                <div class="media">
                    <span class="avatar avatar--lg">{{ initials }}</span>
                    <div>
                        <h2 style="font-size:23px">{{ profile.name }}</h2>
                        <p class="muted">{{ profile.email }}<span v-if="profile.phone"> · {{ profile.phone }}</span></p>
                        <div class="row" style="margin-top:8px"><span class="badge badge--primary">{{ user?.role_label }}</span><span class="badge badge--sage">Doğrulanmış</span></div>
                    </div>
                </div>
            </div>

            <!-- kişisel bilgiler + teslimat adresi -->
            <div class="grid cols-2" style="align-items:start; gap:20px">
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Kişisel bilgiler</p><h2>Hesap bilgilerin</h2></div></div>
                    <div class="stack" style="gap:0">
                        <div class="field" :class="{ 'field--error': infoForm.errors.name }">
                            <label class="label">Ad Soyad</label>
                            <input class="input" v-model="infoForm.name" />
                            <p v-if="infoForm.errors.name" class="error-text">⚠ {{ infoForm.errors.name }}</p>
                        </div>
                        <div class="field">
                            <label class="label">Telefon</label>
                            <div class="input-group"><span class="addon">+90</span><input class="input" v-model="infoForm.phone" /></div>
                        </div>
                        <div class="field" :class="{ 'field--error': infoForm.errors.email }">
                            <label class="label">E-posta</label>
                            <div class="input-icon"><span class="ic">✉</span><input class="input" v-model="infoForm.email" /></div>
                            <p v-if="infoForm.errors.email" class="error-text">⚠ {{ infoForm.errors.email }}</p>
                        </div>
                    </div>
                    <div class="row"><button class="btn btn--primary" :disabled="infoForm.processing" @click="saveInfo">{{ infoForm.processing ? 'Kaydediliyor…' : 'Kaydet' }}</button></div>
                </div>

                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Teslimat adresi</p><h2>Varsayılan adres</h2></div></div>
                    <div class="form-grid">
                        <div class="field"><label class="label">Adres başlığı</label><input class="input" v-model="addressForm.label" /></div>
                        <div class="field"><label class="label">Bölge</label><select class="select" v-model="addressForm.zone_id"><option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option></select></div>
                    </div>
                    <div class="field" :class="{ 'field--error': addressForm.errors.line }">
                        <label class="label">Açık adres</label>
                        <textarea class="textarea" v-model="addressForm.line"></textarea>
                        <p v-if="addressForm.errors.line" class="error-text">⚠ {{ addressForm.errors.line }}</p>
                    </div>
                    <div class="row"><button class="btn btn--primary" :disabled="addressForm.processing" @click="saveAddress">{{ addressForm.processing ? 'Kaydediliyor…' : 'Adresi güncelle' }}</button></div>
                </div>
            </div>

            <!-- banka bilgileri + şifre -->
            <div class="grid cols-2" style="align-items:start; gap:20px">
                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Banka bilgileri</p><h2>İade / çekim hesabı</h2></div></div>
                    <div class="alert alert--info" style="margin-bottom:14px">
                        <span class="alert__ic">ℹ</span>
                        <div>Provizyon iadeleri ve ileride bakiye çekimi için kullanılır. Bilgilerin yalnızca sana gösterilir; kart bilgisi istemeyiz.</div>
                    </div>
                    <div class="field" :class="{ 'field--error': bankForm.errors.iban }">
                        <label class="label">IBAN</label>
                        <input class="input" v-model="bankForm.iban" @blur="formatIban" placeholder="TR00 0000 0000 0000 0000 0000 00" autocomplete="off" spellcheck="false" />
                        <p v-if="bankForm.errors.iban" class="error-text">⚠ {{ bankForm.errors.iban }}</p>
                    </div>
                    <div class="field" :class="{ 'field--error': bankForm.errors.iban_holder }">
                        <label class="label">Hesap sahibi</label>
                        <input class="input" v-model="bankForm.iban_holder" placeholder="Ad Soyad" />
                        <p v-if="bankForm.errors.iban_holder" class="error-text">⚠ {{ bankForm.errors.iban_holder }}</p>
                    </div>
                    <div class="row"><button class="btn btn--primary" :disabled="bankForm.processing" @click="saveBank">{{ bankForm.processing ? 'Kaydediliyor…' : 'Banka bilgilerini kaydet' }}</button></div>
                </div>

                <div class="card">
                    <div class="card__head"><div><p class="eyebrow">Güvenlik</p><h2>Şifre değiştir</h2></div></div>
                    <div class="field" :class="{ 'field--error': passwordForm.errors.current_password }">
                        <label class="label">Mevcut şifre</label>
                        <input class="input" type="password" v-model="passwordForm.current_password" />
                        <p v-if="passwordForm.errors.current_password" class="error-text">⚠ {{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div class="field" :class="{ 'field--error': passwordForm.errors.password }">
                        <label class="label">Yeni şifre</label>
                        <input class="input" type="password" v-model="passwordForm.password" />
                        <p v-if="passwordForm.errors.password" class="error-text">⚠ {{ passwordForm.errors.password }}</p>
                    </div>
                    <div class="field"><label class="label">Yeni şifre tekrar</label><input class="input" type="password" v-model="passwordForm.password_confirmation" /></div>
                    <div class="row"><button class="btn btn--primary" :disabled="passwordForm.processing" @click="savePassword">{{ passwordForm.processing ? 'Kaydediliyor…' : 'Şifreyi değiştir' }}</button></div>
                </div>
            </div>

            <!-- bildirim tercihleri (tam genişlik) -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Bildirimler</p><h2>Bildirim tercihleri</h2></div></div>
                <div class="pref">
                    <div class="pref__text">
                        <b>E-posta bildirimleri</b>
                        <small class="muted">Sipariş durumu değişince e-posta al — kurye atandı, yolda, teslim edildi, ek ödeme.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" v-model="notifyForm.notify_email" />
                        <span class="switch__slider"></span>
                    </label>
                </div>
                <div class="pref">
                    <div class="pref__text">
                        <b>Uygulama içi bildirimler</b>
                        <small class="muted">Zil simgesinde anlık bildirim göster.</small>
                    </div>
                    <label class="switch">
                        <input type="checkbox" v-model="notifyForm.notify_web" />
                        <span class="switch__slider"></span>
                    </label>
                </div>

                <div class="events">
                    <p class="events__title">Hangi durumlarda haber verelim?</p>
                    <p class="muted" style="font-size:13px; margin:0 0 4px">Kapattığın olaylar için ne e-posta ne de zil bildirimi gönderilir.</p>
                    <label v-for="ev in eventList" :key="ev.key" class="event">
                        <span class="event__text"><b>{{ ev.label }}</b><small class="muted">{{ ev.hint }}</small></span>
                        <span class="switch">
                            <input type="checkbox" v-model="notifyForm.events[ev.key]" />
                            <span class="switch__slider"></span>
                        </span>
                    </label>
                </div>

                <div class="row"><button class="btn btn--primary" :disabled="notifyForm.processing" @click="saveNotifications">{{ notifyForm.processing ? 'Kaydediliyor…' : 'Tercihleri kaydet' }}</button></div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.pref { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 15px 0; border-bottom: 1px solid var(--line); }
.pref:last-of-type { border-bottom: 0; }
.pref__text b { display: block; font-size: 15px; }
.pref__text small { display: block; margin-top: 2px; max-width: 540px; }

.switch { position: relative; display: inline-block; width: 48px; height: 28px; flex-shrink: 0; }
.switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.switch__slider { position: absolute; inset: 0; cursor: pointer; background: var(--line); border-radius: 999px; transition: background 0.2s; }
.switch__slider::before { content: ''; position: absolute; height: 22px; width: 22px; left: 3px; top: 3px; background: #fff; border-radius: 50%; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25); transition: transform 0.2s; }
.switch input:checked + .switch__slider { background: var(--primary); }
.switch input:checked + .switch__slider::before { transform: translateX(20px); }
.switch input:focus-visible + .switch__slider { outline: 2px solid var(--primary-2); outline-offset: 2px; }

.events { margin-top: 4px; padding-top: 14px; border-top: 1px dashed var(--line); }
.events__title { font-size: 14px; font-weight: 800; margin-bottom: 2px; }
.event { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; cursor: pointer; }
.event + .event { border-top: 1px solid var(--line); }
.event__text b { display: block; font-size: 14px; font-weight: 600; }
.event__text small { display: block; font-size: 12.5px; margin-top: 1px; }
</style>
