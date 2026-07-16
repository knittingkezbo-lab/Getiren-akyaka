<?php

namespace Tests\Feature;

use App\Payments\DemoGateway;
use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use Tests\TestCase;

/**
 * Üretimde 'demo' sürücüsü ASLA kullanılmamalı: gerçek para hareketi yapmadan
 * siparişleri "ödenmiş" gibi geçirir. Yanlış yapılandırma sessizce çalışmamalı.
 */
class ProductionPaymentGuardTest extends TestCase
{
    private function resolveGateway(): PaymentGateway
    {
        $this->app->forgetInstance(PaymentGateway::class);

        return $this->app->make(PaymentGateway::class);
    }

    public function test_demo_gateway_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['payments.driver' => 'demo']);

        $this->expectException(PaymentException::class);
        $this->resolveGateway();
    }

    public function test_demo_gateway_is_fine_outside_production(): void
    {
        $this->app['env'] = 'local';
        config(['payments.driver' => 'demo']);

        $this->assertInstanceOf(DemoGateway::class, $this->resolveGateway());
    }

    public function test_unknown_driver_fails_loudly(): void
    {
        config(['payments.driver' => 'yok-boyle-bir-surucu']);

        $this->expectException(\InvalidArgumentException::class);
        $this->resolveGateway();
    }
}
