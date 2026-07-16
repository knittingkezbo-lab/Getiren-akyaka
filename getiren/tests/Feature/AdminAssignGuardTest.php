<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yönetici ataması iki şeyi kontrol etmiyordu:
 *
 *  - Kuryenin ONAYLI olup olmadığını: onaysız kurye kurye alanına giremez
 *    (courier.approved middleware), yani iş kimsenin dokunamadığı yerde asılı kalır.
 *  - Siparişin DURUMUNU: teslim edilmiş/iptal siparişe atama yapılabiliyor,
 *    müşteriye "kuryen atandı" bildirimi gidiyordu.
 */
class AdminAssignGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function reservedOrder(): Order
    {
        $this->actingAs($this->makeCustomer())->post('/musteri/siparis', [
            'raw_text' => 'ekmek',
            'zone_id' => Zone::where('key', 'akyaka')->firstOrFail()->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ]);

        return Order::firstOrFail();
    }

    /** Onaysız kuryeye atama yapılamaz: o kurye işi göremez, sipariş asılı kalır. */
    public function test_unapproved_courier_cannot_be_assigned(): void
    {
        $order = $this->reservedOrder();
        $pending = User::factory()->unapproved()->create(['role' => UserRole::Courier]);

        $this->actingAs($this->admin())
            ->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $pending->id])
            ->assertSessionHasErrors('courier_id');

        $order->refresh();
        $this->assertNull($order->courier_id, 'Onaysız kurye atandı — sipariş kimsenin göremeyeceği yerde!');
        $this->assertSame(OrderStatus::Reserved, $order->status);
    }

    /** Kapanmış siparişe atama yapılamaz. */
    public function test_delivered_order_cannot_be_reassigned(): void
    {
        $order = $this->reservedOrder();
        $order->update(['status' => OrderStatus::Delivered, 'delivered_at' => now()]);

        $courier = $this->makeCourier();

        $this->actingAs($this->admin())
            ->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $courier->id])
            ->assertSessionHasErrors();

        $this->assertNull($order->fresh()->courier_id, 'Teslim edilmiş siparişe kurye atandı!');
    }

    /** İptal edilmiş siparişe de atama yapılamaz. */
    public function test_cancelled_order_cannot_be_assigned(): void
    {
        $order = $this->reservedOrder();
        $order->update(['status' => OrderStatus::Cancelled]);

        $this->actingAs($this->admin())
            ->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $this->makeCourier()->id])
            ->assertSessionHasErrors();

        $this->assertNull($order->fresh()->courier_id);
    }

    /** Normal atama çalışmaya devam etmeli — kapı sadece hatalıyı durdurmalı. */
    public function test_approved_courier_is_assigned_to_open_order(): void
    {
        $order = $this->reservedOrder();
        $courier = $this->makeCourier();

        $this->actingAs($this->admin())
            ->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $courier->id])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame($courier->id, $order->courier_id);
        $this->assertSame(OrderStatus::Assigned, $order->status);
    }
}
