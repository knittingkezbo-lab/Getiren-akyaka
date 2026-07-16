<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PriceHint;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierSettleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    /** @return array{0: User, 1: User, 2: Order} müşteri, kurye, alışverişteki sipariş (provizyon 670, fiş 365) */
    private function shoppingOrder(): array
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

        return [$customer, $courier, $order->fresh()];
    }

    private function receiptFromEstimates(Order $order): array
    {
        return $order->items->map(fn ($i) => ['id' => $i->id, 'actual_price' => (float) $i->estimated_price])->all();
    }

    public function test_accept_assigns_courier(): void
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true]);
        $order = Order::firstOrFail();
        $courier = $this->makeCourier();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen")->assertRedirect();

        $order->refresh();
        $this->assertEquals($courier->id, $order->courier_id);
        $this->assertEquals(OrderStatus::Assigned, $order->status);
    }

    /** Fişe göre kes → kalan sağlayıcıda çözülür ("fazlasını iade et"in gerçekleştiği yer). */
    public function test_settle_captures_receipt_and_releases_the_rest(): void
    {
        [, $courier, $order] = $this->shoppingOrder();

        $this->actingAs($courier)
            ->post("/kurye/is/{$order->id}/fis", ['items' => $this->receiptFromEstimates($order)])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(365.0, (float) $order->actual_receipt_amount);
        $this->assertEquals(615.0, (float) $order->captured_amount);   // 365 + 250 hizmet
        $this->assertEquals(55.0, (float) $order->refund_amount);      // 670 - 615

        $auth = $order->authorizations()->sole();
        $this->assertEquals(AuthorizationStatus::Captured, $auth->status);
        $this->assertEquals(670.0, (float) $auth->amount);
        $this->assertEquals(615.0, (float) $auth->captured_amount);
        $this->assertEquals(55.0, $auth->releasedAmount());
        $this->assertAuthorizationsConsistent($order);
    }

    public function test_settle_over_budget_flags_extra_payment_without_touching_the_authorization(): void
    {
        [, $courier, $order] = $this->shoppingOrder();
        $items = $order->items->values();
        $payload = [
            ['id' => $items[0]->id, 'actual_price' => 100],
            ['id' => $items[1]->id, 'actual_price' => 350],
            ['id' => $items[2]->id, 'actual_price' => 50],
        ]; // toplam 500 → 750 gerekiyor > 670 provizyon

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::RequiresExtraPayment, $order->status);
        $this->assertEquals(80.0, (float) $order->extra_required_amount); // 750 - 670

        // Provizyona dokunulmadı: para hareket etmedi, müşterinin onayı bekleniyor
        $auth = $order->authorizations()->sole();
        $this->assertEquals(AuthorizationStatus::Authorized, $auth->status);
        $this->assertNull($auth->captured_amount);
        $this->assertAuthorizationsConsistent($order);
    }

    /**
     * REGRESYON: ilk turda bulduğumuz çift-tahsil hatası.
     * Teslim edilmiş sipariş tekrar settle edilememeli; provizyon ikinci kez kesilmemeli.
     */
    public function test_double_settle_is_rejected_and_authorization_untouched(): void
    {
        [, $courier, $order] = $this->shoppingOrder();
        $payload = $this->receiptFromEstimates($order);

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertRedirect();

        $capturedAt = $order->authorizations()->sole()->captured_at;

        // ikinci settle denemesi → guard reddeder (422)
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertStatus(422);

        $order->refresh();
        $auth = $order->authorizations()->sole(); // hâlâ tek provizyon
        $this->assertEquals(615.0, (float) $auth->captured_amount, 'Çift settle tahsil tutarını değiştirdi!');
        $this->assertEquals($capturedAt, $auth->captured_at, 'Çift settle provizyonu yeniden kesti!');
        $this->assertAuthorizationsConsistent($order);
    }

    public function test_settle_learns_unknown_item_into_dictionary(): void
    {
        $this->assertDatabaseMissing('price_hints', ['keyword' => 'peynir']);

        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => 'peynir', 'zone_id' => $zone->id, 'terms_accepted' => true,
        ]);

        $order = Order::firstOrFail();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);
        $item = $order->items()->firstOrFail();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", [
            'items' => [['id' => $item->id, 'actual_price' => 120]],
        ])->assertRedirect();

        // "peynir" artık sözlükte — gerçek fiş fiyatıyla
        $this->assertDatabaseHas('price_hints', ['keyword' => 'peynir', 'category' => 'öğrenilen']);
        $this->assertEquals(120.0, (float) PriceHint::where('keyword', 'peynir')->value('unit_price'));
    }

    /** Bilinen kalem sözlüğe İKİNCİ kez eklenmez (fiyatı güncellenir — bkz. PriceLearningTest). */
    public function test_settle_does_not_duplicate_known_item(): void
    {
        [, $courier, $order] = $this->shoppingOrder(); // süt/ağrı kesici/ekmek — hepsi sözlükte
        $before = PriceHint::count();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $this->receiptFromEstimates($order)])->assertRedirect();

        $this->assertEquals($before, PriceHint::count()); // yeni kelime eklenmedi
    }

    public function test_courier_cannot_settle_another_couriers_job(): void
    {
        [, , $order] = $this->shoppingOrder();
        $other = $this->makeCourier();

        $this->actingAs($other)
            ->post("/kurye/is/{$order->id}/fis", ['items' => $this->receiptFromEstimates($order)])
            ->assertForbidden();

        $this->assertEquals(OrderStatus::Shopping, $order->refresh()->status);
        $this->assertEquals(AuthorizationStatus::Authorized, $order->authorizations()->sole()->status);
    }
}
