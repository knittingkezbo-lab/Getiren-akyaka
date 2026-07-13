<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderNotification;
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

    public function test_admin_assign_notifies_courier(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $courier = $this->makeCourier();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $courier->id]);

        // Kurye "sana atandı", müşteri "kuryen atandı" bildirimi alır
        $this->assertEquals('Yeni iş atandı', $courier->fresh()->notifications()->first()->data['title']);
        $this->assertEquals("/kurye/is/{$order->id}", $courier->fresh()->notifications()->first()->data['url']);
        $this->assertEquals(1, $customer->fresh()->notifications()->count());
    }

    public function test_new_order_broadcasts_to_couriers(): void
    {
        $courier = $this->makeCourier();
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id]);

        $note = $courier->fresh()->notifications()->first();
        $this->assertNotNull($note);
        $this->assertEquals('Yeni iş fırsatı', $note->data['title']);
        $this->assertEquals('/kurye', $note->data['url']);
        // Yayın bildirimi yalnızca web zil — e-posta göndermez (mail kanalı yok)
    }

    public function test_customer_can_update_notification_preferences(): void
    {
        $customer = $this->makeCustomer(100);

        $this->actingAs($customer)
            ->put('/musteri/profil/bildirimler', ['notify_email' => false, 'notify_web' => true])
            ->assertRedirect();

        $customer->refresh();
        $this->assertFalse($customer->notify_email);
        $this->assertTrue($customer->notify_web);
    }

    public function test_via_respects_channel_preferences(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $notification = new OrderNotification($order, 'Test', 'mesaj');

        $customer->update(['notify_email' => false, 'notify_web' => true]);
        $this->assertEquals(['database'], $notification->via($customer->fresh()));

        $customer->update(['notify_email' => true, 'notify_web' => false]);
        $this->assertEquals(['mail'], $notification->via($customer->fresh()));

        $customer->update(['notify_email' => true, 'notify_web' => true]);
        $this->assertEquals(['database', 'mail'], $notification->via($customer->fresh()));
    }

    public function test_disabling_web_pref_skips_database_notification(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $customer->update(['notify_web' => false]);
        $courier = $this->makeCourier();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen");

        // Web tercihi kapalı → zil (database) bildirimi yazılmaz
        $this->assertEquals(0, $customer->fresh()->notifications()->count());
    }

    public function test_cancelling_assigned_order_notifies_courier(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $courier = $this->makeCourier();
        $order->update(['courier_id' => $courier->id, 'status' => OrderStatus::Assigned]);

        $this->actingAs($customer)->post("/musteri/siparisler/{$order->id}/iptal")->assertRedirect();

        $this->assertEquals(OrderStatus::Cancelled, $order->fresh()->status);
        $note = $courier->fresh()->notifications()->first();
        $this->assertNotNull($note);
        $this->assertEquals('Sipariş iptal edildi', $note->data['title']);
    }

    public function test_double_cancel_is_rejected_without_double_refund(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $wallet = $customer->wallet;

        $this->actingAs($customer)->post("/musteri/siparisler/{$order->id}/iptal")->assertRedirect();
        // İkinci iptal reddedilir (durum artık Cancelled)
        $this->actingAs($customer)->post("/musteri/siparisler/{$order->id}/iptal")->assertStatus(422);

        $wallet->refresh();
        $this->assertEquals(0.0, (float) $wallet->reserved);
        $this->assertEquals(1000.0, (float) $wallet->balance);
        $this->assertEquals(1, $wallet->transactions()->where('type', 'release')->count());
        $this->assertLedgerConsistent($wallet);
    }

    public function test_event_preference_suppresses_both_channels(): void
    {
        [$customer, $order] = $this->reservedOrder();
        $customer->update(['notification_events' => ['assigned' => false]]);
        $courier = $this->makeCourier();

        $this->actingAs($courier)->post("/kurye/is/{$order->id}/ustlen");

        // 'assigned' olayı kapalı → ne zil ne e-posta
        $this->assertEquals(0, $customer->fresh()->notifications()->count());
    }

    public function test_update_notifications_persists_event_map(): void
    {
        $customer = $this->makeCustomer(100);

        $this->actingAs($customer)->put('/musteri/profil/bildirimler', [
            'notify_email' => true,
            'notify_web' => true,
            'events' => ['assigned' => true, 'on_the_way' => false, 'delivered' => true, 'extra' => false],
        ])->assertRedirect();

        $events = $customer->fresh()->notification_events;
        $this->assertTrue($events['assigned']);
        $this->assertFalse($events['on_the_way']);
        $this->assertFalse($events['extra']);
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
