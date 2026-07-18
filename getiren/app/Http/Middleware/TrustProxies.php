<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Güvenilir proxy listesini config'ten okur.
 *
 * Neden ayrı sınıf: liste bootstrap/app.php içinde verilseydi env/config o anda
 * henüz yüklü olmazdı (withMiddleware closure'ı yapılandırma anında koşar). Middleware
 * ise her istekte container'dan çözülür — orada config hazırdır. Böylece liste
 * TRUSTED_PROXIES ile ortamdan yönetilebilir kalır.
 *
 * Ayrıntılı gerekçe: config/security.php
 */
class TrustProxies extends Middleware
{
    public function __construct()
    {
        $proxies = config('security.trusted_proxies');

        // Boş bırakılırsa hiçbir proxy'ye güvenme (Laravel varsayılanı) — '*'a düşme.
        $this->proxies = ($proxies === null || $proxies === '') ? [] : $proxies;
    }
}
