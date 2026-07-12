<?php

namespace Tests\Feature;

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
     * Fiş blokeyi aşan (requires_extra_payment) bir sipariş üretir:
     * bloke 670, fiş 500 + hizmet 250 = 750 tahsil → ek 80.
     *
     * @return array{0: User, 1: Order}
     */
    private function overBudgetOrder(float $customerBalance = 1000): array
    {
        $customer = $this->makeCustomer($customerBalance);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
            'zone_id' => $zone->id,
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

    public function test_customer_pays_extra_and_order_completes(): void
    {
        [$customer, $order] = $this->overBudgetOrder(1000);
        // sipariş sonrası: kullanılabilir 330, bloke 670, ek 80

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/ek-odeme")
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(750.0, (float) $order->captured_amount);

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(250.0, (float) $wallet->balance);   // 330 - 80 ek
        $this->assertEquals(0.0, (float) $wallet->reserved);    // 670 bloke tahsil edildi
        $this->assertLedgerConsistent($wallet);

        $this->assertDatabaseHas('wallet_transactions', [
            'order_id' => $order->id,
            'type' => 'extra_charge',
            'amount' => -80,
        ]);
    }

    public function test_extra_payment_rejected_when_balance_insufficient(): void
    {
        // tam sipariş kadar bakiye → sipariş sonrası kullanılabilir 0, ek 80 karşılanamaz
        [$customer, $order] = $this->overBudgetOrder(670);

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/ek-odeme")
            ->assertSessionHasErrors('extra');

        $this->assertEquals(OrderStatus::RequiresExtraPayment, $order->refresh()->status);
    }

    public function test_extra_payment_rejected_when_not_required(): void
    {
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);
        $order = Order::firstOrFail(); // reserved — ek ödeme gerektirmez

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/ek-odeme")
            ->assertStatus(422);
    }
}
