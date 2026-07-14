<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Aktif ödeme sürücüsü
    |---------------------------------------------------------------------------
    |
    | Uygulama para hareketlerini yalnızca PaymentGateway arayüzü üzerinden yapar:
    | authorize (provizyon al) → capture (fişe göre kes) → void (çöz).
    |
    | 'demo' sürücüsü gerçek para hareketi YAPMAZ; adımları kaydeder ve akışı uçtan
    | uca çalıştırır. Gerçek sağlayıcı (iyzico/PayTR) anahtarları geldiğinde yeni bir
    | sürücü sınıfı aşağıya eklenir ve driver değiştirilir — uygulama kodu değişmez.
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'demo'),

    'drivers' => [
        'demo' => App\Payments\DemoGateway::class,
    ],

];
