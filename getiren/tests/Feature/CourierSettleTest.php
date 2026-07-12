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

class CourierSettleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    /** @return array{0: User, 1: User, 2: Order} müşteri, kurye, alışverişteki sipariş (reserved 670, fiş 365) */
    private function shoppingOrder(): array
    {
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', [
            'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
            'zone_id' => $zone->id,
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
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);
        $order = Order::firstOrFail();
        $courier = $this->makeCourier();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen")->assertRedirect();

        $order->refresh();
        $this->assertEquals($courier->id, $order->courier_id);
        $this->assertEquals(OrderStatus::Assigned, $order->status);
    }

    public function test_settle_captures_and_refunds_difference(): void
    {
        [$customer, $courier, $order] = $this->shoppingOrder();

        $this->actingAs($courier)
            ->post("/kurye/is/{$order->id}/fis", ['items' => $this->receiptFromEstimates($order)])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(365.0, (float) $order->actual_receipt_amount);
        $this->assertEquals(615.0, (float) $order->captured_amount);   // 365 + 250 hizmet
        $this->assertEquals(55.0, (float) $order->refund_amount);      // 670 - 615

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(385.0, (float) $wallet->balance);          // 330 + 55 iade
        $this->assertEquals(0.0, (float) $wallet->reserved);
        $this->assertLedgerConsistent($wallet);

        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'capture']);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'refund', 'amount' => 55]);
    }

    public function test_settle_over_budget_flags_extra_payment_without_moving_money(): void
    {
        [$customer, $courier, $order] = $this->shoppingOrder();
        $items = $order->items->values();
        $payload = [
            ['id' => $items[0]->id, 'actual_price' => 100],
            ['id' => $items[1]->id, 'actual_price' => 350],
            ['id' => $items[2]->id, 'actual_price' => 50],
        ]; // toplam 500 → tahsil 750 > 670 bloke

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::RequiresExtraPayment, $order->status);
        $this->assertEquals(80.0, (float) $order->extra_required_amount); // 750 - 670

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(330.0, (float) $wallet->balance);   // değişmedi
        $this->assertEquals(670.0, (float) $wallet->reserved);  // değişmedi
        $this->assertLedgerConsistent($wallet);
        $this->assertDatabaseMissing('wallet_transactions', ['order_id' => $order->id, 'type' => 'capture']);
    }

    /**
     * REGRESYON: ilk turda bulduğumuz çift-tahsil hatası.
     * Teslim edilmiş sipariş tekrar settle edilememeli; para ikinci kez hareket etmemeli.
     */
    public function test_double_settle_is_rejected_and_funds_untouched(): void
    {
        [$customer, $courier, $order] = $this->shoppingOrder();
        $payload = $this->receiptFromEstimates($order);

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertRedirect();

        $wallet = $customer->wallet->refresh();
        $balanceAfter = (float) $wallet->balance;
        $txCount = $wallet->transactions()->count();

        // ikinci settle denemesi → guard reddeder (422)
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload])->assertStatus(422);

        $wallet->refresh();
        $this->assertEquals($balanceAfter, (float) $wallet->balance, 'Çift settle bakiyeyi değiştirdi!');
        $this->assertEquals($txCount, $wallet->transactions()->count(), 'Çift settle fazladan hareket yazdı!');
        $this->assertLedgerConsistent($wallet);
    }

    public function test_courier_cannot_settle_another_couriers_job(): void
    {
        [$customer, $courier, $order] = $this->shoppingOrder();
        $other = $this->makeCourier();

        $this->actingAs($other)
            ->post("/kurye/is/{$order->id}/fis", ['items' => $this->receiptFromEstimates($order)])
            ->assertForbidden();

        $this->assertEquals(OrderStatus::Shopping, $order->refresh()->status);
    }
}
