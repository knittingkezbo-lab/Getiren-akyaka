<?php

return [
    /*
     | Kayıtta e-posta doğrulama zorunluluğu.
     | Kapalıyken (demo/geliştirme) kayıt anında doğrulanır ve doğrulama kapısı uygulanmaz.
     | Gerçek bir e-posta sağlayıcısı bağlanınca AUTH_EMAIL_VERIFICATION=true ile açılır.
     */
    'email_verification' => (bool) env('AUTH_EMAIL_VERIFICATION', false),

    // Sipariş onayında kabul edilen ön bilgilendirme/şartlar sürümü (siparişe kaydedilir)
    'terms_version' => '2026-07-legal-v1',

    /*
     | Canlı güncelleme (Reverb/WebSocket). Kapalıyken (LIVE_UPDATES=false) uygulama
     | Reverb'siz çalışır: bildirimler DB + e-postaya gider, anlık push gönderilmez ve
     | tarayıcı WebSocket'e bağlanmaya çalışmaz. Talep gelince tek anahtarla açılır
     | (LIVE_UPDATES=true + VITE_LIVE_UPDATES=true + Reverb servisi + asset build).
     */
    'live_updates' => (bool) env('LIVE_UPDATES', true),
];
