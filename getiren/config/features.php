<?php

return [
    /*
     | Kayıtta e-posta doğrulama zorunluluğu.
     | Kapalıyken (demo/geliştirme) kayıt anında doğrulanır ve doğrulama kapısı uygulanmaz.
     | Gerçek bir e-posta sağlayıcısı bağlanınca AUTH_EMAIL_VERIFICATION=true ile açılır.
     */
    'email_verification' => (bool) env('AUTH_EMAIL_VERIFICATION', false),
];
