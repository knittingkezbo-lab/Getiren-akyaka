<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentAuthorization;
use App\Models\Zone;
use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    public function test_creating_order_authorizes_the_estimated_amount(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)
            ->post('/musteri/siparis', [
                'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
                'zone_id' => $zone->id,
                'terms_accepted' => true,
            ])
            ->assertRedirect();

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(OrderStatus::Reserved, $order->status);
        $this->assertEquals(670.0, (float) $order->reserved_amount);
        $this->assertEquals(3, $order->items()->count());

        // Tahmin edilen tutar kadar provizyon açıldı; henüz hiçbir şey kesilmedi
        $auth = $order->authorizations()->sole();
        $this->assertEquals(AuthorizationStatus::Authorized, $auth->status);
        $this->assertEquals(670.0, (float) $auth->amount);
        $this->assertNull($auth->captured_amount);
        $this->assertAuthorizationsConsistent($order);
    }

    /**
     * Sağlayıcı reddederse (gerçek PSP'de kart reddi) sipariş HİÇ oluşmamalı —
     * transaction geri alınır, ortada yetim sipariş kalmaz.
     */
    public function test_order_is_not_created_when_the_gateway_declines(): void
    {
        $this->swap(PaymentGateway::class, new class implements PaymentGateway
        {
            public function authorize(Order $order, float $amount, ?string $note = null): PaymentAuthorization
            {
                throw new PaymentException('Kart reddedildi.');
            }

            public function capture(PaymentAuthorization $authorization, float $amount): PaymentAuthorization
            {
                throw new PaymentException('kullanılmıyor');
            }

            public function void(PaymentAuthorization $authorization): PaymentAuthorization
            {
                throw new PaymentException('kullanılmıyor');
            }
        });

        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)
            ->post('/musteri/siparis', [
                'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
                'zone_id' => $zone->id,
                'terms_accepted' => true,
            ])
            ->assertSessionHasErrors('raw_text');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_authorizations', 0);
    }

    public function test_cancelling_reserved_order_voids_the_authorization(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true]);
        $order = Order::firstOrFail();

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/iptal")
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status);

        // Provizyon hiç kesilmeden tamamen çözüldü — tutarın tamamı müşteriye bırakıldı
        $auth = $order->authorizations()->sole();
        $this->assertEquals(AuthorizationStatus::Voided, $auth->status);
        $this->assertEquals(0.0, (float) $auth->captured_amount);
        $this->assertEquals((float) $order->reserved_amount, $auth->releasedAmount());
        $this->assertAuthorizationsConsistent($order);
    }

    public function test_customer_cannot_cancel_others_order(): void
    {
        $owner = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($owner)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true]);
        $order = Order::firstOrFail();

        $intruder = $this->makeCustomer();
        $this->actingAs($intruder)
            ->post("/musteri/siparisler/{$order->id}/iptal")
            ->assertForbidden();

        $this->assertEquals(OrderStatus::Reserved, $order->refresh()->status);
        $this->assertEquals(AuthorizationStatus::Authorized, $order->authorizations()->sole()->status);
    }

    public function test_order_rejected_without_terms_acceptance(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)
            ->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]) // onay yok
            ->assertSessionHasErrors('terms_accepted');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_authorizations', 0);
    }

    public function test_order_stores_terms_version(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true,
        ]);

        $this->assertEquals(config('features.terms_version'), Order::firstOrFail()->terms_version);
    }
}
