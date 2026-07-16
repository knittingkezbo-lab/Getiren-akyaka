<?php

namespace Tests\Feature;

use App\Enums\PriceSource;
use App\Enums\UserRole;
use App\Models\PriceHint;
use App\Models\User;
use Database\Seeders\PriceHintSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceImportTest extends TestCase
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

    private function import(string $text)
    {
        return $this->actingAs($this->admin())->post('/yonetici/ayarlar/fiyat-ice-aktar', ['text' => $text]);
    }

    public function test_imports_new_items_with_category_and_price(): void
    {
        $this->import("peynir; Market; 240\nzeytin; Market; 180")->assertRedirect()->assertSessionHasNoErrors();

        $peynir = PriceHint::whereRaw('LOWER(keyword) = ?', ['peynir'])->sole();
        $this->assertEquals(240.0, (float) $peynir->unit_price);
        $this->assertEquals('Market', $peynir->category);
        $this->assertEquals(PriceSource::Manual, $peynir->source);
    }

    public function test_two_column_format_without_category_works(): void
    {
        $this->import('peynir; 240')->assertRedirect();

        $this->assertEquals(240.0, (float) PriceHint::whereRaw('LOWER(keyword) = ?', ['peynir'])->sole()->unit_price);
    }

    public function test_turkish_decimal_and_tl_suffix_are_parsed(): void
    {
        $this->import('peynir; Market; 240,50 TL')->assertRedirect();

        $this->assertEquals(240.50, (float) PriceHint::whereRaw('LOWER(keyword) = ?', ['peynir'])->sole()->unit_price);
    }

    public function test_import_updates_existing_manual_item(): void
    {
        $this->import('süt; Market; 78')->assertRedirect();

        $sut = PriceHint::whereRaw('LOWER(keyword) = ?', ['süt'])->sole();
        $this->assertEquals(78.0, (float) $sut->unit_price); // tohumlanan 50 → 78
    }

    /** EN ÖNEMLİSİ: sahadan öğrenilen gerçek fiyat, elle içe aktarmayla EZİLMEZ. */
    public function test_import_never_overwrites_observed_real_price(): void
    {
        $sut = PriceHint::whereRaw('LOWER(keyword) = ?', ['süt'])->sole();
        $sut->recordObservation(95); // kurye gerçekte 95 TL ödemiş

        $this->import('süt; Market; 40')->assertRedirect();

        $sut->refresh();
        $this->assertEquals(95.0, (float) $sut->unit_price, 'Gerçek fiyat korunmalı');
        $this->assertEquals(PriceSource::Observed, $sut->source);
    }

    public function test_blank_lines_comments_and_garbage_are_skipped(): void
    {
        $before = PriceHint::count();

        $this->import("\n# yorum satırı\nbozuk satır\nx; 5\n")->assertRedirect();

        // 'x' çok kısa (min 2), 'bozuk satır' ayrıştırılamaz → hiçbiri eklenmez
        $this->assertEquals($before, PriceHint::count());
    }

    public function test_import_is_audited(): void
    {
        $this->import('peynir; Market; 240')->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_only_admin_can_import(): void
    {
        $this->actingAs($this->makeCustomer())
            ->post('/yonetici/ayarlar/fiyat-ice-aktar', ['text' => 'peynir; 240'])
            ->assertForbidden();

        $this->assertDatabaseMissing('price_hints', ['keyword' => 'peynir']);
    }
}
