<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_iban_normalized(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => 'tr96 3456 7890 1234 5678 9012 34', // boşluklu + küçük harf
            'iban_holder' => 'Gencer Ger',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('TR963456789012345678901234', $customer->iban); // boşluk yok, büyük harf
        $this->assertEquals('Gencer Ger', $customer->iban_holder);
    }

    public function test_invalid_iban_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => 'TR123', // geçersiz (24 rakam değil)
            'iban_holder' => 'X',
        ])->assertSessionHasErrors('iban');

        $this->assertNull($customer->fresh()->iban);
    }

    public function test_courier_can_save_iban(): void
    {
        $courier = $this->makeCourier();

        $this->actingAs($courier)->put('/kurye/tercihler/banka', [
            'iban' => 'TR13 7654 3210 9876 5432 1098 76',
            'iban_holder' => 'Mert Kaya',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('TR137654321098765432109876', $courier->fresh()->iban);
    }

    public function test_empty_iban_clears_bank_info(): void
    {
        $customer = $this->makeCustomer();
        $customer->update(['iban' => 'TR963456789012345678901234', 'iban_holder' => 'Eski']);

        $this->actingAs($customer)->put('/musteri/profil/banka', ['iban' => '', 'iban_holder' => 'Eski'])
            ->assertRedirect();

        $customer->refresh();
        $this->assertNull($customer->iban);
        $this->assertNull($customer->iban_holder); // iban boşsa hesap sahibi de temizlenir
    }
}
