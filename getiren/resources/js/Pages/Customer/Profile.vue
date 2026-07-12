<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    profile: { type: Object, required: true },
    address: { type: Object, default: null },
    zones: { type: Array, default: () => [] },
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

const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () =>
    passwordForm.put('/musteri/profil/sifre', { preserveScroll: true, onSuccess: () => passwordForm.reset() });
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

            <!-- kişisel bilgiler -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Kişisel bilgiler</p><h2>Hesap bilgilerin</h2></div></div>
                <div class="form-grid">
                    <div class="field" :class="{ 'field--error': infoForm.errors.name }">
                        <label class="label">Ad Soyad</label>
                        <input class="input" v-model="infoForm.name" />
                        <p v-if="infoForm.errors.name" class="error-text">⚠ {{ infoForm.errors.name }}</p>
                    </div>
                    <div class="field">
                        <label class="label">Telefon</label>
                        <div class="input-group"><span class="addon">+90</span><input class="input" v-model="infoForm.phone" /></div>
                    </div>
                    <div class="field full" :class="{ 'field--error': infoForm.errors.email }">
                        <label class="label">E-posta</label>
                        <div class="input-icon"><span class="ic">✉</span><input class="input" v-model="infoForm.email" /></div>
                        <p v-if="infoForm.errors.email" class="error-text">⚠ {{ infoForm.errors.email }}</p>
                    </div>
                </div>
                <div class="row"><button class="btn btn--primary" :disabled="infoForm.processing" @click="saveInfo">{{ infoForm.processing ? 'Kaydediliyor…' : 'Kaydet' }}</button></div>
            </div>

            <!-- adres -->
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

            <!-- güvenlik -->
            <div class="card">
                <div class="card__head"><div><p class="eyebrow">Güvenlik</p><h2>Şifre değiştir</h2></div></div>
                <div class="field" style="max-width:420px" :class="{ 'field--error': passwordForm.errors.current_password }">
                    <label class="label">Mevcut şifre</label>
                    <input class="input" type="password" v-model="passwordForm.current_password" />
                    <p v-if="passwordForm.errors.current_password" class="error-text">⚠ {{ passwordForm.errors.current_password }}</p>
                </div>
                <div class="form-grid" style="max-width:860px">
                    <div class="field" :class="{ 'field--error': passwordForm.errors.password }">
                        <label class="label">Yeni şifre</label>
                        <input class="input" type="password" v-model="passwordForm.password" />
                        <p v-if="passwordForm.errors.password" class="error-text">⚠ {{ passwordForm.errors.password }}</p>
                    </div>
                    <div class="field"><label class="label">Yeni şifre tekrar</label><input class="input" type="password" v-model="passwordForm.password_confirmation" /></div>
                </div>
                <div class="row"><button class="btn btn--primary" :disabled="passwordForm.processing" @click="savePassword">{{ passwordForm.processing ? 'Kaydediliyor…' : 'Şifreyi değiştir' }}</button></div>
            </div>
        </div>
    </AppLayout>
</template>
