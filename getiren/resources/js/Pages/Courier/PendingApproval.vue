<script setup>
import { Head, router } from '@inertiajs/vue3';

// Onaylandıysa /kurye panele yönlendirir, değilse tekrar bu ekrana düşer
const check = () => router.get('/kurye');
const logout = () => router.post('/logout');
</script>

<template>
    <Head title="Onay bekleniyor" />

    <div class="authpage">
        <div class="authcard">
            <!-- sol: marka -->
            <div class="authside">
                <span class="brand"><span class="brand__mark">◐</span> getiren<b>akyaka</b></span>
                <h2>Başvurun<br />alındı.</h2>
                <p>Kurye ekibimize katılmak üzeresin — son onay için başvurunu inceliyoruz.</p>
                <ul class="authpoints">
                    <li><span class="tick">✓</span> Bilgilerin bize ulaştı</li>
                    <li><span class="tick">✓</span> Yönetici onayı bekleniyor</li>
                    <li><span class="tick">✓</span> Onaylanınca iş almaya başla</li>
                </ul>
            </div>

            <!-- sağ: içerik -->
            <div class="authbody">
                <p class="eyebrow">Neredeyse tamam</p>
                <h2 style="font-size:26px; margin:6px 0 4px">Onay bekleniyor</h2>
                <p class="muted" style="margin-bottom:18px">
                    Kurye başvurun ekibimizce inceleniyor. Onaylandığında kurye paneline giriş yapabilirsin.
                </p>

                <button class="btn btn--primary btn--block btn--lg" @click="check">Durumu kontrol et</button>

                <p class="center muted" style="margin-top:22px">
                    <a href="#" style="font-weight:800; color:var(--primary-2)" @click.prevent="logout">Çıkış yap</a>
                </p>
            </div>
        </div>
    </div>
</template>
