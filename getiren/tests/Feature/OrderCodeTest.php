<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sipariş kodu 'A24-'.(max(id)+114) ile üretiliyordu. İki sorun:
 *
 *  - Kod siparişin KENDİ id'sinden değil, tablonun en büyüğünden türüyordu: sayaç
 *    geriye gidebilir (satır silinir) ve var olan bir kodla çakışır. code kolonu
 *    unique olduğu için müşteri 500 yer.
 *  - 'A24' elle gömülüydü — takvim 2026 olsa da kod 24 diyordu.
 *
 * Kod, satırın kendi id'sinden türemeli: benzersizliği veritabanı garanti eder.
 */
class OrderCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function placeOrder(string $text = 'ekmek'): Order
    {
        $this->actingAs($this->makeCustomer())->post('/musteri/siparis', [
            'raw_text' => $text,
            'zone_id' => Zone::where('key', 'akyaka')->firstOrFail()->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    /** Sayaç geriye gidince var olan kodla çakışmamalı. */
    public function test_code_does_not_collide_after_a_row_is_removed(): void
    {
        $first = $this->placeOrder('ekmek');
        $second = $this->placeOrder('süt');

        $keptCode = $first->code;
        $second->delete(); // max(id) geriye gitti

        $third = $this->placeOrder('su');

        $this->assertNotSame($keptCode, $third->code, 'Yeni sipariş var olan bir kodu tekrarladı!');
        $this->assertSame(2, Order::whereIn('code', [$keptCode, $third->code])->count());
    }

    /** Kod, siparişin kendi id'sini taşımalı — böylece benzersizliği DB garanti eder. */
    public function test_code_is_derived_from_the_orders_own_id(): void
    {
        $order = $this->placeOrder();

        $this->assertStringEndsWith('-'.$order->id, $order->code, "Kod kendi id'sinden türemiyor: {$order->code}");
    }

    /** Yıl elle gömülü olmamalı. */
    public function test_code_carries_the_current_year(): void
    {
        $order = $this->placeOrder();

        $this->assertStringStartsWith('A'.now()->format('y').'-', $order->code, "Kodun yılı bugüne ait değil: {$order->code}");
    }

    public function test_codes_are_distinct_across_many_orders(): void
    {
        $codes = collect(range(1, 5))->map(fn () => $this->placeOrder()->code);

        $this->assertCount(5, $codes->unique(), 'Kodlar tekrar etti: '.$codes->implode(', '));
    }
}
