<?php

use App\Payments\DemoGateway;
use App\Payments\PayTRGateway;

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
    | uca çalıştırır. 'paytr' gerçek sağlayıcıdır (anahtarlar gelince aktifleşir).
    |
    */

    'driver' => env('PAYMENT_DRIVER', 'demo'),

    'drivers' => [
        'demo' => DemoGateway::class,
        'paytr' => PayTRGateway::class,
    ],

    /*
    |---------------------------------------------------------------------------
    | PayTR
    |---------------------------------------------------------------------------
    |
    | ÖNEMLİ: Provizyon modelimiz PayTR'de "Ön Provizyon" (blokeli ödeme) yetkisi
    | gerektirir — başvuruda ayrıca talep edilmeli. Standart hesap yalnızca anında
    | tahsilat verir; o durumda authorize→capture akışı yeniden düşünülmeli.
    |
    | Kart bilgisi HİÇBİR ZAMAN bizim sunucumuza gelmez; müşteri PayTR'nin
    | sayfasında/iframe'inde girer (PCI yükü sağlayıcıdadır). Bkz. PayTRGateway.
    |
    */

    /*
    |---------------------------------------------------------------------------
    | PayTR callback kapısı
    |---------------------------------------------------------------------------
    |
    | Entegrasyon (imza doğrulama dahil) tamamlanana kadar KAPALI. Kapalıyken
    | /odeme/paytr/geri-bildirim 404 döner — imzasız bir POST'un sipariş durumunu
    | değiştirmesi mümkün olmasın. Yalnızca uçtan uca test edildikten sonra açılır.
    |
    */

    'callback_enabled' => (bool) env('PAYTR_CALLBACK_ENABLED', false),

    'paytr' => [
        'merchant_id' => env('PAYTR_MERCHANT_ID'),
        'merchant_key' => env('PAYTR_MERCHANT_KEY'),
        'merchant_salt' => env('PAYTR_MERCHANT_SALT'),
        'test_mode' => (bool) env('PAYTR_TEST_MODE', true),
        'base_url' => env('PAYTR_BASE_URL', 'https://www.paytr.com'),
        // PayTR'nin bizim callback'imize POST atacağı adres (panelde de tanımlanır)
        'callback_url' => env('PAYTR_CALLBACK_URL', env('APP_URL').'/odeme/paytr/geri-bildirim'),
    ],

];
