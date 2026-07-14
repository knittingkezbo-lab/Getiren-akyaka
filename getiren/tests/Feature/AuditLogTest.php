<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Yönetici Ayşe']);
    }

    public function test_courier_approval_is_recorded(): void
    {
        $admin = $this->makeAdmin();
        $courier = User::factory()->unapproved()->create(['role' => UserRole::Courier, 'name' => 'Mert Kaya']);

        $this->actingAs($admin)->post("/yonetici/kuryeler/{$courier->id}/onayla")->assertRedirect();

        $log = AuditLog::sole();
        $this->assertEquals(AuditAction::CourierApproved, $log->action);
        $this->assertEquals('Yönetici Ayşe', $log->actor_name);
        $this->assertEquals($admin->id, $log->actor_id);
        $this->assertEquals('Mert Kaya', $log->subject_label);
        $this->assertEquals($courier->id, $log->subject_id);
    }

    public function test_rejected_courier_stays_readable_after_the_user_row_is_deleted(): void
    {
        $admin = $this->makeAdmin();
        $courier = User::factory()->unapproved()->create(['role' => UserRole::Courier, 'name' => 'Silinecek Kurye']);

        $this->actingAs($admin)->post("/yonetici/kuryeler/{$courier->id}/reddet")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $courier->id]);

        // Hedef satır gitti ama kayıt hâlâ "kimi reddettik" sorusunu cevaplıyor
        $log = AuditLog::sole();
        $this->assertEquals(AuditAction::CourierRejected, $log->action);
        $this->assertEquals('Silinecek Kurye', $log->subject_label);
        $this->assertEquals($courier->email, $log->meta['e-posta']);
    }

    public function test_order_assignment_is_recorded(): void
    {
        $admin = $this->makeAdmin();
        $courier = $this->makeCourier();
        $customer = $this->makeCustomer(1000);
        $zone = Zone::where('key', 'akyaka')->first();

        $this->actingAs($customer)->post('/musteri/siparis', ['raw_text' => 'ekmek', 'zone_id' => $zone->id, 'terms_accepted' => true]);
        $order = Order::firstOrFail();

        $this->actingAs($admin)->post("/yonetici/siparisler/{$order->id}/ata", ['courier_id' => $courier->id])->assertRedirect();

        $log = AuditLog::where('action', AuditAction::OrderAssigned)->sole();
        $this->assertEquals("#{$order->code}", $log->subject_label);
        $this->assertEquals($courier->name, $log->meta['kurye']);
    }

    public function test_setting_change_records_old_and_new_value(): void
    {
        $admin = $this->makeAdmin();
        Setting::put('safety_buffer_pct', 15);

        $this->actingAs($admin)->post('/yonetici/ayarlar', [
            'zones' => [],
            'priceHints' => [],
            'settings' => [
                'safety_buffer_pct' => 25,
                'min_order_total' => 100,
                'accepting_orders' => true,
                'auto_assign_courier' => false,
            ],
        ])->assertRedirect();

        $log = AuditLog::where('action', AuditAction::SettingsUpdated)->sole();
        $this->assertEquals(['eski' => '15', 'yeni' => 25], $log->meta['changes']['Güvenlik payı (%)']);
        $this->assertStringContainsString('Güvenlik payı', $log->description);
    }

    public function test_saving_settings_without_changes_writes_no_log(): void
    {
        $admin = $this->makeAdmin();

        $payload = [
            'zones' => [],
            'priceHints' => [],
            'settings' => [
                'safety_buffer_pct' => (float) Setting::get('safety_buffer_pct', 15),
                'min_order_total' => (float) Setting::get('min_order_total', 100),
                'accepting_orders' => (bool) Setting::get('accepting_orders', 1),
                'auto_assign_courier' => (bool) Setting::get('auto_assign_courier', 0),
            ],
        ];

        // İlk kayıt farkı yazabilir (tohumlanan değerler string), ikincisinde hiçbir şey değişmez
        $this->actingAs($admin)->post('/yonetici/ayarlar', $payload);
        $before = AuditLog::count();

        $this->actingAs($admin)->post('/yonetici/ayarlar', $payload);

        $this->assertEquals($before, AuditLog::count());
    }

    public function test_audit_log_cannot_be_modified_or_deleted(): void
    {
        $log = AuditLog::record(AuditAction::CourierApproved, 'test');

        try {
            $log->update(['description' => 'değiştirildi']);
            $this->fail('Denetim kaydı güncellenebildi — değiştirilemez olmalıydı.');
        } catch (RuntimeException) {
            // beklenen
        }

        try {
            $log->delete();
            $this->fail('Denetim kaydı silinebildi — silinemez olmalıydı.');
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertEquals('test', $log->fresh()->description);
    }

    public function test_only_admin_can_see_the_audit_log(): void
    {
        $this->actingAs($this->makeCustomer())->get('/yonetici/denetim')->assertForbidden();
        $this->actingAs($this->makeCourier())->get('/yonetici/denetim')->assertForbidden();
        $this->actingAs($this->makeAdmin())->get('/yonetici/denetim')->assertOk();
    }
}
