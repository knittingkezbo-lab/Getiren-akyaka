<?php

namespace App\Providers;

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
