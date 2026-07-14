<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    /**
     * Fiş provizyonu aşan (requires_extra_payment) bir sipariş üretir:
     * provizyon 670, fiş 500 + hizmet 250 = 750 gerekiyor → ek 80.
     *
     * @return array{0: User, 1: Order}
     */
    private function overBudgetOrder(): array
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
            'zone_id' => $zone->id,
            'terms_accepted' => true,
        ]);

        $order = Order::firstOrFail();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);

        $items = $order->items->values();
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => [
            ['id' => $items[0]->id, 'actual_price' => 100],
            ['id' => $items[1]->id, 'actual_price' => 350],
            ['id' => $items[2]->id, 'actual_price' => 50],
        ]]);

        return [$customer, $order->fresh()];
    }

    public function test_over_budget_order_is_flagged_for_extra_payment(): void
    {
        [, $order] = $this->overBudgetOrder();

        $this->assertEquals(OrderStatus::RequiresExtraPayment, $order->status);
        $this->assertEquals(80.0, (float) $order->extra_required_amount);
    }

    /**
     * Ek ödeme: ilk provizyon tamamen kesilir, FARK ayrı bir çekim olarak alınır.
     * Gerçek sağlayıcıda da provizyondan fazlası tahsil edilemez — bu yüzden iki kayıt.
     */
    public function test_customer_pays_extra_and_order_completes(): void
    {
        [$customer, $order] = $this->overBudgetOrder();

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/ek-odeme")
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(750.0, (float) $order->captured_amount);
        $this->assertEquals(0.0, (float) $order->refund_amount);

        $auths = $order->authorizations()->orderBy('id')->get();
        $this->assertCount(2, $auths);

        // 1) sipariş provizyonu: 670 alındı, 670 kesildi (geri bırakılan yok)
        $this->assertEquals(AuthorizationStatus::Captured, $auths[0]->status);
        $this->assertEquals(670.0, (float) $auths[0]->amount);
        $this->assertEquals(670.0, (float) $auths[0]->captured_amount);

        // 2) ek ödeme: 80 alındı, 80 kesildi
        $this->assertEquals(AuthorizationStatus::Captured, $auths[1]->status);
        $this->assertEquals(80.0, (float) $auths[1]->amount);
        $this->assertEquals(80.0, (float) $auths[1]->captured_amount);

        // toplam kesilen = siparişin tahsil tutarı
        $this->assertEquals(750.0, (float) $auths->sum('captured_amount'));
        $this->assertAuthorizationsConsistent($order);
    }

    public function test_double_extra_payment_is_rejected(): void
    {
        [$customer, $order] = $this->overBudgetOrder();

        $this->actingAs($customer)->post("/musteri/siparisler/{$order->id}/ek-odeme")->assertRedirect();

        // ikinci deneme: sipariş artık delivered → guard reddeder, para ikinci kez çekilmez
        $this->actingAs($customer)->post("/musteri/siparisler/{$order->id}/ek-odeme")->assertStatus(422);

        $order->refresh();
        $this->assertEquals(750.0, (float) $order->authorizations()->sum('captured_amount'));
        $this->assertCount(2, $order->authorizations()->get());
        $this->assertAuthorizationsConsistent($order);
    }

    public function test_extra_payment_rejected_when_not_required(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true]);
        $order = Order::firstOrFail(); // reserved — ek ödeme gerektirmez

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/ek-odeme")
            ->assertStatus(422);
    }
}
