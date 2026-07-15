<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Company;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ZoneSeeder::class, PriceHintSeeder::class, SettingSeeder::class]);
    }

    private function basePayload(array $company = [], ?bool $draft = null): array
    {
        return [
            'zones' => [],
            'priceHints' => [],
            'settings' => [
                'safety_buffer_pct' => (float) Setting::get('safety_buffer_pct', 15),
                'min_order_total' => (float) Setting::get('min_order_total', 100),
                'accepting_orders' => (bool) Setting::get('accepting_orders', 1),
                'auto_assign_courier' => (bool) Setting::get('auto_assign_courier', 0),
            ],
            'company' => $company,
            'legal_draft' => $draft ?? true,
        ];
    }

    public function test_company_get_prefers_db_over_config(): void
    {
        config(['company.legal_name' => 'Env Unvan']);
        $this->assertEquals('Env Unvan', Company::get('legal_name'));

        Setting::put('company_legal_name', 'DB Unvan');
        $this->assertEquals('DB Unvan', Company::get('legal_name'));
    }

    public function test_admin_can_update_company_info_and_it_shows_on_legal_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post('/yonetici/ayarlar', $this->basePayload([
                'legal_name' => 'Mevlüt Yıldız',
                'tax_office' => 'Muğla',
                'tax_no' => '26012225460',
                'address' => 'Menteşe / Muğla',
                'email' => 'iletisim@getirenakyaka.com',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals('Mevlüt Yıldız', Company::get('legal_name'));

        // İletişim sayfası artık gerçek bilgiyi içerir (ASCII vergi no ile doğrula —
        // Inertia HTML'de Türkçe karakterleri \u.. olarak kaçırabilir)
        $html = $this->actingAs($admin)->get('/hukuki/iletisim')->getContent();
        $this->assertStringContainsString('26012225460', $html);
    }

    public function test_company_change_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post('/yonetici/ayarlar', $this->basePayload(['phone' => '0505 000 00 00']));

        $log = AuditLog::where('action', AuditAction::SettingsUpdated)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('Şirket · Telefon', $log->meta['changes']);
    }

    public function test_clearing_tax_no_hides_it_from_contact_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Setting::put('company_tax_no', '26012225460');
        Setting::put('company_tax_office', 'Muğla');

        // Vergi no'yu boşalt → sayfada görünmemeli
        $this->actingAs($admin)->post('/yonetici/ayarlar', $this->basePayload(['tax_no' => '']));

        $this->assertEquals('', Company::get('tax_no'));
        $html = $this->actingAs($admin)->get('/hukuki/iletisim')->getContent();
        $this->assertStringNotContainsString('26012225460', $html);
    }

    public function test_draft_toggle_controls_banner_flag(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post('/yonetici/ayarlar', $this->basePayload([], draft: false));

        $this->assertFalse(Company::draft());
    }

    public function test_only_admin_can_update_company_info(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/yonetici/ayarlar', $this->basePayload(['legal_name' => 'Sızma']))
            ->assertForbidden();

        // Hiçbir override yazılmadı (env/config'ten bağımsız doğrulama)
        $this->assertNull(Setting::get('company_legal_name'));
    }
}
