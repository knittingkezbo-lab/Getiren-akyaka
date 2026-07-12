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

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    public function test_creating_order_blocks_funds_and_writes_hold(): void
    {
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)
            ->post('/musteri/siparis', [
                'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
                'zone_id' => $zone->id,
            ])
            ->assertRedirect();

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals(OrderStatus::Reserved, $order->status);
        $this->assertEquals(670.0, (float) $order->reserved_amount);
        $this->assertEquals(3, $order->items()->count());

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(330.0, (float) $wallet->balance);
        $this->assertEquals(670.0, (float) $wallet->reserved);
        $this->assertLedgerConsistent($wallet);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'order_id' => $order->id,
            'type' => 'hold',
            'amount' => -670,
        ]);
    }

    public function test_order_rejected_when_balance_insufficient(): void
    {
        $customer = $this->makeCustomer(100);
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)
            ->post('/musteri/siparis', [
                'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek',
                'zone_id' => $zone->id,
            ])
            ->assertSessionHasErrors('raw_text');

        $this->assertDatabaseCount('orders', 0);
        $this->assertEquals(100.0, (float) $customer->wallet->refresh()->balance);
        $this->assertDatabaseMissing('wallet_transactions', ['wallet_id' => $customer->wallet->id, 'type' => 'hold']);
    }

    public function test_cancelling_reserved_order_releases_hold(): void
    {
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);
        $order = Order::firstOrFail();

        $this->actingAs($customer)
            ->post("/musteri/siparisler/{$order->id}/iptal")
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status);

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(1000.0, (float) $wallet->balance);
        $this->assertEquals(0.0, (float) $wallet->reserved);
        $this->assertLedgerConsistent($wallet);
    }

    public function test_customer_cannot_cancel_others_order(): void
    {
        $owner = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($owner)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);
        $order = Order::firstOrFail();

        $intruder = $this->makeCustomer(1000);
        $this->actingAs($intruder)
            ->post("/musteri/siparisler/{$order->id}/iptal")
            ->assertForbidden();

        $this->assertEquals(OrderStatus::Reserved, $order->refresh()->status);
    }
}
