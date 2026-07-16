<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PriceHint;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finansal onay ekranında İKİ ayrı tahmin algoritması olamaz.
 *
 * Vue kendi hesabını yapıyordu (bilinmeyen kalem 40 TL + %15 pay); sunucu ise
 * 60 TL + %35 ile provizyona alıyordu. Yani müşteri gördüğü/onayladığı tutardan
 * FARKLI bir tutar bloke ediliyordu. Tek otorite sunucu olmalı.
 */
class OrderEstimateEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function zone(): Zone
    {
        return Zone::where('key', 'akyaka')->firstOrFail();
    }

    public function test_endpoint_returns_estimate_for_known_item(): void
    {
        $this->actingAs($this->makeCustomer())
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => 'süt', 'zone_id' => $this->zone()->id])
            ->assertOk()
            ->assertJsonStructure(['items', 'items_total', 'safety_buffer', 'service_fee', 'reserved_amount', 'unknown_count', 'buffer_pct'])
            ->assertJsonPath('unknown_count', 0)
            ->assertJsonPath('buffer_pct', 15);
    }

    /** Bilinmeyen kalemde ekranda GERÇEK pay (%35) görünmeli — Vue'daki sabit %15 değil. */
    public function test_endpoint_reports_real_buffer_for_unknown_item(): void
    {
        $this->actingAs($this->makeCustomer())
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => "Ayışığı'ndan 2 adana", 'zone_id' => $this->zone()->id])
            ->assertOk()
            ->assertJsonPath('unknown_count', 1)
            ->assertJsonPath('buffer_pct', 35);
    }

    /**
     * ASIL GARANTİ: önizlemede gösterilen tutar ile store()'un provizyona aldığı
     * tutar birebir aynı olmalı.
     */
    public function test_previewed_total_equals_the_amount_actually_authorized(): void
    {
        $customer = $this->makeCustomer();
        $text = "Ayışığı'ndan 2 adana, süt";

        $preview = $this->actingAs($customer)
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => $text, 'zone_id' => $this->zone()->id])
            ->assertOk()
            ->json('reserved_amount');

        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => $text,
            'zone_id' => $this->zone()->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertEquals($preview, (float) $order->reserved_amount, 'Önizleme ile provizyon tutarı farklı!');
        $this->assertEquals($preview, (float) $order->authorizations()->sole()->amount);
    }

    public function test_inactive_zone_is_rejected(): void
    {
        $passive = Zone::where('is_active', false)->first() ?? Zone::factory()?->create(['is_active' => false]);
        $this->assertNotNull($passive, 'Pasif bölge bulunamadı — ZoneSeeder Ataköy pasif olmalı');

        $this->actingAs($this->makeCustomer())
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => 'süt', 'zone_id' => $passive->id])
            ->assertStatus(422);
    }

    public function test_estimate_requires_authenticated_customer(): void
    {
        $this->postJson('/musteri/siparis/tahmin', ['raw_text' => 'süt', 'zone_id' => $this->zone()->id])
            ->assertUnauthorized();

        $this->actingAs($this->makeCourier())
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => 'süt', 'zone_id' => $this->zone()->id])
            ->assertForbidden();
    }

    /**
     * Fiyat sözlüğü müşteriye gitmemeli: tahmin artık sunucuda yapılıyor, istemcinin
     * fiyatlara ihtiyacı yok. Otomatik tamamlama yalnızca kelimeleri kullanır.
     * Sözlük bizim tahmin zekâmız — data-page JSON'ında herkese açık durmamalı.
     */
    public function test_create_page_does_not_leak_price_dictionary(): void
    {
        // Fiyatı kesin bilinen, ASCII bir kelime seç: Türkçe karakterler JSON'da \uXXXX kaçışlanır
        $hint = PriceHint::where('is_active', true)->firstOrFail();
        $hint->update(['keyword' => 'testkalem', 'unit_price' => 137.42]);

        $response = $this->actingAs($this->makeCustomer())->get('/musteri/siparis/yeni')->assertOk();

        // Kelime otomatik tamamlama için lazım — kalmalı
        $response->assertSee('testkalem');

        // Ama fiyatı asla
        $response->assertDontSee('137.42');

        $hints = $response->viewData('page')['props']['priceHints'];
        $this->assertNotEmpty($hints, 'Otomatik tamamlama için kelimeler gitmeli');
        foreach ($hints as $h) {
            $this->assertArrayNotHasKey('unit_price', (array) $h, 'Fiyat sözlüğü istemciye sızıyor!');
        }
    }
}
