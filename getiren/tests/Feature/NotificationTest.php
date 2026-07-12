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

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function reservedOrder(): array
    {
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();
        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);

        return [$customer, Order::firstOrFail()];
    }

    public function test_courier_accept_notifies_customer(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $courier = $this->makeCourier();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen");

        $customer->refresh();
        $this->assertEquals(1, $customer->notifications()->count());
        $this->assertEquals(1, $customer->unreadNotifications()->count());
        $this->assertEquals('Kuryen atandı', $customer->notifications()->first()->data['title']);
    }

    public function test_settle_notifies_customer_of_delivery(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Shopping]);

        $payload = $order->items->map(fn ($i) => ['id' => $i->id, 'actual_price' => (float) $i->estimated_price])->all();
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/fis", ['items' => $payload]);

        $customer->refresh();
        $this->assertEquals('Siparişin teslim edildi', $customer->notifications()->first()->data['title']);
    }

    public function test_mark_all_read_clears_unread(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $courier = $this->makeCourier();
        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen");
        $this->assertEquals(1, $customer->fresh()->unreadNotifications()->count());

        $this->actingAs($customer)->post('/bildirimler/oku')->assertRedirect();

        $this->assertEquals(0, $customer->fresh()->unreadNotifications()->count());
    }
}
