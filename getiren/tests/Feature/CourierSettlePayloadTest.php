<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fiş, müşterinin kartından ne kesileceğini belirleyen tek girdi. settle() gelen
 * satırları teker teker toplayıp bulamadığını sessizce atlıyordu:
 *
 *  - aynı kalem iki kez gönderilirse fiş şişiyordu   → müşteriden FAZLA tahsilat
 *  - kalem eksik/yabancı gönderilirse sessizce atlanıyordu → EKSİK fiş, zararı biz yeriz
 *
 * Fiş, siparişin kalem kümesinin BİREBİR aynısı olmalı; değilse hiçbir yan etki
 * olmadan reddedilmeli.
 */
class CourierSettlePayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    /** @return array{0: User, 1: Order} kurye, alışverişteki 3 kalemli sipariş */
    private function shoppingOrder(): array
    {
        $customer = $this->makeCustomer();
        $zone = Zone::where('key', 'akyaka')->firstOrFail();

        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
            'zone_id' => $zone->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ]);

        $order = Order::firstOrFail();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);

        return [$courier, $order->fresh()];
    }

    /** Aynı kalemi iki kez göndermek fişi şişirmemeli. */
    public function test_duplicate_item_ids_are_rejected(): void
    {
        [$courier, $order] = $this->shoppingOrder();
        $first = $order->items->first();

        // Tekrarı 'distinct' kuralı yakalar; hata anahtarı items.0.id / items.1.id olur
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", [
            'items' => [
                ['id' => $first->id, 'actual_price' => 50],
                ['id' => $first->id, 'actual_price' => 50],
            ],
        ])->assertSessionHasErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Shopping, $order->status, 'Reddedilen fiş siparişi kapattı!');
        $this->assertNull($order->actual_receipt_amount);
        $this->assertAuthorizationsConsistent($order);
    }

    /** Kalem eksik bırakılamaz: fiş kısmi gönderilip sipariş kapanmamalı. */
    public function test_missing_items_are_rejected(): void
    {
        [$courier, $order] = $this->shoppingOrder();
        $this->assertGreaterThan(1, $order->items->count());

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", [
            'items' => [['id' => $order->items->first()->id, 'actual_price' => 50]],
        ])->assertSessionHasErrors('items');

        $order->refresh();
        $this->assertSame(OrderStatus::Shopping, $order->status, 'Eksik fişle sipariş kapandı!');
        $this->assertNull($order->actual_receipt_amount);
    }

    /** Başka siparişin kalemi sessizce atlanmamalı, reddedilmeli. */
    public function test_foreign_item_id_is_rejected(): void
    {
        [$courier, $order] = $this->shoppingOrder();

        // Başka bir müşterinin siparişinden kalem
        $otherCustomer = $this->makeCustomer();
        $this->actingAs($otherCustomer)->post('/musteri/siparis', [
            'raw_text' => 'gazete',
            'zone_id' => Zone::where('key', 'akyaka')->firstOrFail()->id,
            'address_text' => 'Akyaka',
            'terms_accepted' => true,
        ]);
        $foreign = Order::where('id', '!=', $order->id)->firstOrFail()->items()->firstOrFail();

        $payload = $order->items->map(fn (OrderItem $i) => ['id' => $i->id, 'actual_price' => 40])->all();
        $payload[] = ['id' => $foreign->id, 'actual_price' => 999];

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])
            ->assertSessionHasErrors('items');

        $order->refresh();
        $this->assertSame(OrderStatus::Shopping, $order->status);
        $this->assertNull($foreign->fresh()->actual_price, 'Yabancı kalemin fiyatı değişti!');
    }

    /** Tam ve doğru fiş normal şekilde geçmeli — kapı sadece hatalıyı durdurmalı. */
    public function test_exact_item_set_settles_normally(): void
    {
        [$courier, $order] = $this->shoppingOrder();

        $payload = $order->items->map(fn (OrderItem $i) => ['id' => $i->id, 'actual_price' => 30])->all();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(30.0 * count($payload), (float) $order->actual_receipt_amount);
        $this->assertAuthorizationsConsistent($order);
    }
}
