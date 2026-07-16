<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * accept() route binding'den gelen ESKİ anlık görüntüyü kontrol edip sonra yazıyordu:
 * iki kurye aynı anda "üstlen" derse ikisi de kontrolü geçip ikincisi birincinin
 * üstüne yazabiliyordu — biri kendisinde olmayan iş için Akyaka'ya gidiyordu.
 *
 * Koşul artık UPDATE'in içinde: kazananı veritabanı seçer.
 */
class JobClaimRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function reservedOrder(): Order
    {
        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => 'ekmek',
            'zone_id' => Zone::where('key', 'akyaka')->firstOrFail()->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ]);

        return Order::firstOrFail();
    }

    /** İkinci kurye işi çalamaz; hata sayfası değil, anlaşılır bir mesaj görür. */
    public function test_second_courier_cannot_steal_a_claimed_job(): void
    {
        $order = $this->reservedOrder();
        $first = $this->makeCourier();
        $second = $this->makeCourier();

        $this->actingAs($first)->post("/kurye/is/{$order->id}/ustlen")->assertRedirect();

        $this->actingAs($second)->post("/kurye/is/{$order->id}/ustlen")
            ->assertRedirect(route('courier.dashboard'))
            ->assertSessionHas('error');

        $order->refresh();
        $this->assertSame($first->id, $order->courier_id, 'İkinci kurye işi çaldı!');
        $this->assertSame(OrderStatus::Assigned, $order->status);
    }

    /**
     * Asıl koruma: kontrol UPDATE'in içinde olmalı. Satır kapıldıktan SONRA gelen
     * istek, elindeki eski "boşta" görüntüsüne rağmen hiçbir satır güncelleyememeli.
     */
    public function test_claim_update_is_conditional_not_read_then_write(): void
    {
        $order = $this->reservedOrder();
        $first = $this->makeCourier();
        $second = $this->makeCourier();

        // İkinci kuryenin elindeki eski görüntü: sipariş hâlâ boşta görünüyor
        $staleSnapshot = Order::findOrFail($order->id);
        $this->assertNull($staleSnapshot->courier_id);

        // Bu sırada birinci kurye işi kapıyor
        $this->actingAs($first)->post("/kurye/is/{$order->id}/ustlen")->assertRedirect();

        // Eski görüntüyle gelen istek tek satır bile güncelleyememeli
        $claimed = Order::whereKey($staleSnapshot->id)
            ->where('status', OrderStatus::Reserved)
            ->whereNull('courier_id')
            ->update(['courier_id' => $second->id, 'status' => OrderStatus::Assigned]);

        $this->assertSame(0, $claimed, 'Koşullu UPDATE eski görüntüyle satırı ezdi!');
        $this->assertSame($first->id, $order->fresh()->courier_id);
    }
}
