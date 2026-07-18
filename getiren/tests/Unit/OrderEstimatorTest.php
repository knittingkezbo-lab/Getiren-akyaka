<?php

namespace Tests\Unit;

use App\Models\PriceHint;
use App\Models\Zone;
use App\Services\OrderEstimator;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private OrderEstimator $estimator;

    private Zone $akyaka;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
        $this->estimator = app(OrderEstimator::class);
        $this->akyaka = Zone::where('key', 'akyaka')->firstOrFail(); // hizmet bedeli 250
    }

    public function test_estimates_products_buffer_and_fee(): void
    {
        // süt 50 + (2 × ağrı kesici 150 = 300) + ekmek 15 = 365
        $est = $this->estimator->estimate('1 kutu süt, 2 ağrı kesici, ekmek', $this->akyaka);

        $this->assertEquals(365.0, $est['items_total']);
        $this->assertEquals(55.0, $est['safety_buffer']);   // ceil(365 × 0.15)
        $this->assertEquals(250.0, $est['service_fee']);
        $this->assertEquals(670.0, $est['reserved_amount']);
        $this->assertCount(3, $est['items']);
    }

    public function test_multiword_keyword_matches(): void
    {
        // "ağrı kesici" tek anahtar olarak eşleşir (150), "ağrı" değil
        $est = $this->estimator->estimate('2 ağrı kesici', $this->akyaka);
        $this->assertEquals(300.0, $est['items_total']);
    }

    public function test_su_does_not_falsely_match_suyu(): void
    {
        // "su" kelime sınırı: "suyu" içinde eşleşmemeli → bilinmeyen kalem (40), min 100 tabanı
        $est = $this->estimator->estimate('kavun suyu', $this->akyaka);
        $this->assertEquals(100.0, $est['items_total']);

        // "3 su" ise gerçekten su (35) → 105
        $est2 = $this->estimator->estimate('3 su', $this->akyaka);
        $this->assertEquals(105.0, $est2['items_total']);
    }

    public function test_min_order_total_is_floored(): void
    {
        // tek ekmek 15 → min_order_total 100 tabanına yükselir
        $est = $this->estimator->estimate('ekmek', $this->akyaka);
        $this->assertEquals(100.0, $est['items_total']);
    }

    public function test_zone_fee_is_used(): void
    {
        $gokova = Zone::where('key', 'gokova')->firstOrFail(); // 350
        $est = $this->estimator->estimate('ekmek', $gokova);
        $this->assertEquals(350.0, $est['service_fee']);
    }

    public function test_ve_conjunction_splits_customer_items(): void
    {
        $est = $this->estimator->estimate('2 ekmek ve 1 süt', $this->akyaka);

        $this->assertCount(2, $est['items']);
        $this->assertSame(0, $est['unknown_count']);
        $this->assertEquals([30.0, 50.0], array_column($est['items'], 'estimated_price'));
    }

    public function test_unit_spacing_is_normalized_when_matching(): void
    {
        PriceHint::create(['keyword' => 'toz şeker 5 kg', 'category' => 'Market', 'unit_price' => 199, 'is_active' => true]);
        PriceHint::create(['keyword' => 'makarna 500 gr', 'category' => 'Market', 'unit_price' => 30, 'is_active' => true]);

        $est = $this->estimator->estimate('1 toz şeker 5kg, 2 makarna 500gr', $this->akyaka);

        $this->assertSame(0, $est['unknown_count']);
        $this->assertEquals([199.0, 60.0], array_column($est['items'], 'estimated_price'));
    }

    public function test_customer_language_synonyms_match_poultry_items(): void
    {
        PriceHint::create(['keyword' => 'piliç baget kg', 'category' => 'Market', 'unit_price' => 145, 'is_active' => true]);
        PriceHint::create(['keyword' => 'piliç bonfile kg', 'category' => 'Market', 'unit_price' => 289, 'is_active' => true]);

        $est = $this->estimator->estimate('2 kilo tavuk baget, 1 kilo tavuk göğsü', $this->akyaka);

        $this->assertSame(0, $est['unknown_count']);
        $this->assertEquals(579.0, $est['items_total']);
        $this->assertEquals([290.0, 289.0], array_column($est['items'], 'estimated_price'));
    }

    public function test_trailing_quantity_is_detected_for_packaged_item(): void
    {
        PriceHint::create(['keyword' => 'yumurta 30lu', 'category' => 'Market', 'unit_price' => 129, 'is_active' => true]);

        $est = $this->estimator->estimate('yumurta 30ludan 2 tane', $this->akyaka);

        $this->assertSame(0, $est['unknown_count']);
        $this->assertEquals('Yumurta 30Lu', $est['items'][0]['name']);
        $this->assertSame(2, $est['items'][0]['qty']);
        $this->assertEquals(258.0, $est['items'][0]['estimated_price']);
    }

    public function test_leading_quantity_does_not_satisfy_package_size_token(): void
    {
        PriceHint::create(['keyword' => 'ayçiçek yağı 1lt', 'category' => 'Market', 'unit_price' => 119, 'is_active' => true]);
        PriceHint::create(['keyword' => 'ayçiçek yağı 5lt', 'category' => 'Market', 'unit_price' => 459, 'is_active' => true]);

        $est = $this->estimator->estimate('1 ayçiçek yağı 5lt', $this->akyaka);

        $this->assertSame(0, $est['unknown_count']);
        $this->assertEquals('Ayçiçek Yağı 5Lt', $est['items'][0]['name']);
        $this->assertEquals(459.0, $est['items'][0]['estimated_price']);
    }
}
