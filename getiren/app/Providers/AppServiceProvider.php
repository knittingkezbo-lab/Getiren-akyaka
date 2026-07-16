<?php

namespace App\Providers;

use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ödeme sağlayıcısı config'ten seçilir; uygulama yalnızca arayüzü bilir
        $this->app->singleton(PaymentGateway::class, function ($app) {
            $driver = config('payments.driver');
            $class = config("payments.drivers.{$driver}");

            if (! $class) {
                throw new InvalidArgumentException("Tanımsız ödeme sürücüsü: [{$driver}]");
            }

            // Fail-closed: demo sürücüsü gerçek para hareketi YAPMAZ; üretimde
            // kullanılırsa siparişler ödenmemişken "ödenmiş" gibi geçer. Yanlış
            // yapılandırma sessizce çalışmaktansa gürültüyle patlasın.
            if ($driver === 'demo' && $app->environment('production')) {
                throw new PaymentException(
                    'Üretimde demo ödeme sürücüsü kullanılamaz: gerçek tahsilat yapmaz. '.
                    'PAYMENT_DRIVER değerini gerçek bir sağlayıcıya ayarlayın.',
                );
            }

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
