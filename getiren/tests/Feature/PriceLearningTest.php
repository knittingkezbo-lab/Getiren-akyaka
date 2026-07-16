<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PriceSource;
use App\Models\Order;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;
use App\Services\OrderEstimator;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceLearningTest extends TestCase
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

    /** Sezonluk iş: ilk gerçek fiyat, tahmini/referansı BEKLETMEDEN ezmeli. */
    public function test_first_real_price_replaces_the_guess_immediately(): void
    {
        $hint = PriceHint::create(['keyword' => 'peynir', 'unit_price' => 100, 'is_active' => true]);

        $hint->recordObservation(180);

        $hint->refresh();
        $this->assertEquals(180.0, (float) $hint->unit_price, 'İlk gözlem tahmini doğrudan ezmeli');
        $this->assertEquals(PriceSource::Observed, $hint->source);
        $this->assertEquals(1, $hint->observed_count);
        $this->assertNotNull($hint->last_observed_at);
    }

    /** Sonraki gözlemler yumuşatılır — tek bir uç fiyat sözlüğü bozmasın. */
    public function test_later_observations_are_smoothed(): void
    {
        $hint = PriceHint::create(['keyword' => 'peynir', 'unit_price' => 100, 'is_active' => true]);

        $hint->recordObservation(200); // ilk: doğrudan 200
        $hint->recordObservation(300); // ikinci: 200*(0.6) + 300*(0.4) = 240

        $hint->refresh();
        $this->assertEquals(240.0, (float) $hint->unit_price);
        $this->assertEquals(2, $hint->observed_count);
    }

    public function test_zero_or_negative_observation_is_ignored(): void
    {
        $hint = PriceHint::create(['keyword' => 'peynir', 'unit_price' => 100, 'is_active' => true]);

        $hint->recordObservation(0);

        $hint->refresh();
        $this->assertEquals(100.0, (float) $hint->unit_price);
        $this->assertEquals(0, $hint->observed_count);
    }

    /** ASIL KAZANIM: kurye gerçek fiyatı girince BİLİNEN kalemin sözlük fiyatı tazelenir. */
    public function test_settle_refreshes_known_item_price_from_real_entry(): void
    {
        $sut = PriceHint::whereRaw('LOWER(keyword) = ?', ['süt'])->firstOrFail();
        $this->assertEquals(50.0, (float) $sut->unit_price); // tohumlanan tahmin

        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => 'süt', 'zone_id' => $this->zone()->id, 'terms_accepted' => true,
        ]);

        $order = Order::firstOrFail();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);
        $item = $order->items()->firstOrFail();

        // Kurye gerçekte 78 TL ödemiş (fiş ya da elle giriş — fark etmez)
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", [
            'items' => [['id' => $item->id, 'actual_price' => 78]],
        ])->assertRedirect();

        $sut->refresh();
        $this->assertEquals(78.0, (float) $sut->unit_price, 'Sözlük gerçek fiyata güncellenmeli');
        $this->assertEquals(PriceSource::Observed, $sut->source);
    }

    /** Çok adetli kalemde BİRİM fiyat öğrenilmeli (satır toplamı değil). */
    public function test_learning_uses_unit_price_not_line_total(): void
    {
        $customer = $this->makeCustomer();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => '3 ekmek', 'zone_id' => $this->zone()->id, 'terms_accepted' => true,
        ]);

        $order = Order::firstOrFail();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);
        $item = $order->items()->firstOrFail();
        $this->assertEquals(3, $item->qty);

        // 3 ekmek toplam 60 TL → birim 20 TL
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", [
            'items' => [['id' => $item->id, 'actual_price' => 60]],
        ])->assertRedirect();

        $ekmek = PriceHint::whereRaw('LOWER(keyword) = ?', ['ekmek'])->firstOrFail();
        $this->assertEquals(20.0, (float) $ekmek->unit_price);
    }

    /** Tanınmayan kalem varsa güvenlik payı yükselmeli — ek ödeme sürtünmesini azaltır. */
    public function test_unknown_item_raises_the_safety_buffer(): void
    {
        $estimator = app(OrderEstimator::class);

        $known = $estimator->estimate('süt', $this->zone());
        $unknown = $estimator->estimate('zzzbilinmeyenurun', $this->zone());

        $this->assertEquals(0, $known['unknown_count']);
        $this->assertEquals(15.0, $known['buffer_pct']);

        $this->assertEquals(1, $unknown['unknown_count']);
        $this->assertEquals(35.0, $unknown['buffer_pct'], 'Bilinmeyen kalemde pay yükselmeli');
    }

    /** Bilinmeyen kalem, ayarlanabilir varsayılan fiyatı kullanmalı (kör sabit değil). */
    public function test_unknown_item_uses_configurable_fallback_price(): void
    {
        Setting::put('fallback_item_price', 90);

        $result = app(OrderEstimator::class)->estimate('zzzbilinmeyenurun', $this->zone());

        $this->assertEquals(90.0, $result['items'][0]['estimated_price']);
        $this->assertFalse($result['items'][0]['known']);
    }

    public function test_known_item_is_marked_known(): void
    {
        $result = app(OrderEstimator::class)->estimate('süt', $this->zone());

        $this->assertTrue($result['items'][0]['known']);
    }

    /** Haftalık gözden geçirme: gerçek fiyatı görülmemiş kalemler listelenebilmeli. */
    public function test_needs_review_scope_finds_unobserved_and_stale_items(): void
    {
        $fresh = PriceHint::create(['keyword' => 'taze', 'unit_price' => 10, 'is_active' => true]);
        $fresh->recordObservation(12); // az önce gözlendi → gözden geçirme gerekmez

        $stale = PriceHint::create(['keyword' => 'bayat', 'unit_price' => 10, 'is_active' => true]);
        $stale->recordObservation(12);
        $stale->update(['last_observed_at' => now()->subDays(PriceHint::STALE_DAYS + 1)]);

        $keywords = PriceHint::needsReview()->pluck('keyword')->all();

        $this->assertNotContains('taze', $keywords);
        $this->assertContains('bayat', $keywords);
        $this->assertContains('süt', $keywords); // hiç gerçek fiyat görülmemiş (tohumlanan tahmin)
    }
}
