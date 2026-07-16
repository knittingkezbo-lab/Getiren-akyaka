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
 * address_text 'nullable' idi ve form alanı yalnızca kayıtlı adresi olanlara
 * gösteriliyordu: yeni bir müşteri adressiz sipariş verebiliyordu. Kurye teslim
 * edeceği yeri bilmiyor, ama müşterinin kartında provizyon çoktan alınmış oluyordu.
 */
class OrderAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'raw_text' => 'ekmek',
            'zone_id' => Zone::where('key', 'akyaka')->firstOrFail()->id,
            'address_text' => 'Akyaka Merkez, Çamlık Sk. No:5',
            'terms_accepted' => true,
        ], $override);
    }

    public function test_order_without_address_is_rejected(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload(['address_text' => null]))
            ->assertSessionHasErrors('address_text');

        $this->assertSame(0, Order::count(), 'Adressiz sipariş oluştu — kurye nereye gidecek?');
    }

    public function test_blank_address_is_rejected(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload(['address_text' => '   ']))
            ->assertSessionHasErrors('address_text');

        $this->assertSame(0, Order::count());
    }

    /** Anlamsız kısa adres de kabul edilmemeli. */
    public function test_too_short_address_is_rejected(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload(['address_text' => 'x']))
            ->assertSessionHasErrors('address_text');
    }

    public function test_order_with_address_is_accepted(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload())
            ->assertRedirect();

        $this->assertSame('Akyaka Merkez, Çamlık Sk. No:5', Order::firstOrFail()->address_text);
    }
}
