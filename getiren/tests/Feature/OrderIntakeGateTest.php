<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAuthorization;
use App\Models\Setting;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Siparişleri kabul et" anahtarı yönetici ekranında "Kapatınca yeni sipariş
 * alınmaz" diyor — ama store() bu ayara hiç bakmıyordu. Yönetici anahtarı
 * kapatıp dükkânı kapattığını sanırken siparişler akmaya devam ediyordu.
 *
 * Bir anahtarın yalan söylemesi, hiç olmamasından kötüdür: kimse kuryesizken
 * gelen siparişin provizyonunu üstlenmek istemez.
 */
class OrderIntakeGateTest extends TestCase
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

    private function payload(): array
    {
        return [
            'raw_text' => 'süt',
            'zone_id' => $this->zone()->id,
            'address_text' => 'Akyaka Merkez',
            'terms_accepted' => true,
        ];
    }

    public function test_order_is_created_while_intake_is_open(): void
    {
        Setting::put('accepting_orders', 1);

        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload())
            ->assertRedirect();

        $this->assertSame(1, Order::count());
    }

    /** Kapalıyken sipariş OLUŞMAMALI ve hiçbir provizyon alınmamalı. */
    public function test_order_is_rejected_while_intake_is_closed(): void
    {
        Setting::put('accepting_orders', 0);

        $this->actingAs($this->makeCustomer())
            ->post('/musteri/siparis', $this->payload())
            ->assertSessionHasErrors('raw_text');

        $this->assertSame(0, Order::count(), 'Kabul kapalıyken sipariş oluştu!');
        $this->assertSame(0, PaymentAuthorization::count(), 'Sipariş yokken müşterinin kartında provizyon var!');
    }

    /** Tahmin önizlemesi de kapalıyken müşteriyi oyalamamalı. */
    public function test_estimate_endpoint_reports_closed_intake(): void
    {
        Setting::put('accepting_orders', 0);

        $this->actingAs($this->makeCustomer())
            ->postJson('/musteri/siparis/tahmin', ['raw_text' => 'süt', 'zone_id' => $this->zone()->id])
            ->assertStatus(422);
    }

    /** Sipariş ekranı kapalı olduğunu söylemeli — kullanıcı boşuna form doldurmasın. */
    public function test_create_page_exposes_closed_state(): void
    {
        Setting::put('accepting_orders', 0);

        $page = $this->actingAs($this->makeCustomer())
            ->get('/musteri/siparis/yeni')
            ->assertOk()
            ->viewData('page');

        $this->assertFalse($page['props']['acceptingOrders']);
    }
}
