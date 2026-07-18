<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\PaymentAuthorization;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PayTR callback'i entegrasyon bitene kadar KAPALI olmalı.
 *
 * Şu an rota herkese açık ve hash doğrulaması yorumda — yani imzasız bir POST
 * siparişin durumunu değiştirebilir. Entegrasyon tamamlanana kadar yol kapalı olsun.
 */
class PaymentCallbackDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function pendingAuthorization(): PaymentAuthorization
    {
        $order = $this->makeCustomer()->ordersAsCustomer()->create([
            'code' => 'T-1',
            'zone_id' => Zone::where('key', 'akyaka')->value('id'),
            'raw_text' => 'ekmek',
            'items_total' => 100,
            'safety_buffer' => 0,
            'service_fee' => 0,
            'reserved_amount' => 100,
            'status' => OrderStatus::Draft,
        ]);

        return PaymentAuthorization::create([
            'order_id' => $order->id,
            'provider' => 'paytr',
            'provider_ref' => 'GA1T123',
            'amount' => 100,
            'status' => AuthorizationStatus::Pending,
        ]);
    }

    public function test_callback_is_disabled_by_default(): void
    {
        $this->postJson('/odeme/paytr/geri-bildirim', [
            'merchant_oid' => 'GA1T123',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => 'sahte',
        ])->assertNotFound();
    }

    /** Kapalıyken imzasız bir POST hiçbir ödeme/sipariş kaydını değiştirmemeli. */
    public function test_disabled_callback_has_no_side_effect(): void
    {
        $auth = $this->pendingAuthorization();

        $this->postJson('/odeme/paytr/geri-bildirim', [
            'merchant_oid' => 'GA1T123',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => 'sahte',
        ])->assertNotFound();

        $auth->refresh();
        $this->assertEquals(AuthorizationStatus::Pending, $auth->status, 'Kapalı callback provizyonu değiştirdi!');
        $this->assertEquals(OrderStatus::Draft, $auth->order->refresh()->status, 'Kapalı callback siparişi değiştirdi!');
    }

    public function test_callback_is_reachable_when_explicitly_enabled(): void
    {
        config(['payments.callback_enabled' => true]);

        // Bilinmeyen merchant_oid → sessizce OK (PayTR tekrar denemesin), ama 404 değil
        $this->postJson('/odeme/paytr/geri-bildirim', [
            'merchant_oid' => 'BILINMEYEN',
            'status' => 'success',
            'total_amount' => '10000',
            'hash' => 'x',
        ])->assertOk();
    }
}
