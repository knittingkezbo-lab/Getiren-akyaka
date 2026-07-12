<?php

namespace Tests\Unit;

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
}
