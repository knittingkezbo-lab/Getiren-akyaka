<?php

use App\Http\Middleware\EnsureCourierApproved;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS-sonlandıran proxy/tünel (Cloudflare vb.) arkasında https şemasını doğru algıla.
        // Liste '*' DEĞİL: config/security.php'den okunur, çünkü '*' istemcinin kendi IP'sini
        // uydurmasına izin verirdi ve IP tabanlı giriş limiti/denetim kaydı anlamsızlaşırdı.
        $middleware->replace(
            TrustProxies::class,
            App\Http\Middleware\TrustProxies::class,
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'courier.approved' => EnsureCourierApproved::class,
        ]);

        // Giriş yapmış kullanıcı misafir sayfalarına (login/register) gelirse
        $middleware->redirectUsersTo('/');

        // PayTR callback'i dış sunucudan gelir; CSRF token taşımaz
        $middleware->validateCsrfTokens(except: [
            'odeme/paytr/geri-bildirim',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia istekleri (X-Inertia) hata akışını kendi yönetir: doğrulama hataları
        // redirect+session ile döner, JSON'a çevrilmemeli. Ama gerçek JSON istemcileri
        // (ör. canlı tahmin endpoint'i) 422/401'i JSON olarak almalı — yoksa istemci
        // hatayı okuyamaz, sessizce login sayfasının HTML'ini yer.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || (! $request->hasHeader('X-Inertia') && $request->expectsJson()),
        );
    })->create();
