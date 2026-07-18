<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Güvenilir proxy'ler
    |--------------------------------------------------------------------------
    |
    | Uygulama nginx'in arkasında çalışır: php-fpm'in gördüğü bağlantı adresi her
    | zaman nginx'tir. Gerçek ziyaretçi IP'si yalnızca X-Forwarded-For başlığından
    | okunabilir — ama o başlığa ancak GÜVENİLİR bir proxy'den geldiğinde inanılır.
    |
    | Neden '*' değil: '*' "her kim bağlanırsa bağlansın, gönderdiği X-Forwarded-For
    | doğrudur" demektir. O zaman istemci kendi IP'sini uydurabilir; e-posta+IP giriş
    | limiti (LoginController) ve denetim kaydındaki IP anlamsızlaşır.
    |
    | Neden "hiçbiri" de değil: o durumda her ziyaretçi nginx'in IP'si olarak görünür,
    | yani herkes tek bir IP'ye toplanır — limit ve audit yine bozulur.
    |
    | Varsayılan özel ağ aralıklarıdır (Docker/nginx bu aralıkta). Bir saldırgan
    | internetten bu aralıktan bağlanamaz; güven kararı doğrudan bağlanan adrese
    | (nginx) göre verilir, başlığa göre değil.
    |
    | ÜRETİM: Gerçek topoloji belli olunca (VDS'teki nginx, Cloudflare vb.) burayı
    | TRUSTED_PROXIES ile o sağlayıcının CIDR listesine daralt.
    |
    */

    'trusted_proxies' => env(
        'TRUSTED_PROXIES',
        '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1'
    ),

];
