<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_save_iban_normalized(): void
    {
        $customer = $this->makeCustomer(0);

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => 'tr12 3456 7890 1234 5678 9012 34', // boşluklu + küçük harf
            'iban_holder' => 'Gencer Ger',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertEquals('TR123456789012345678901234', $customer->iban); // boşluk yok, büyük harf
        $this->assertEquals('Gencer Ger', $customer->iban_holder);
    }

    public function test_invalid_iban_is_rejected(): void
    {
        $customer = $this->makeCustomer(0);

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => 'TR123', // geçersiz (24 rakam değil)
            'iban_holder' => 'X',
        ])->assertSessionHasErrors('iban');

        $this->assertNull($customer->fresh()->iban);
    }

    public function test_empty_iban_clears_bank_info(): void
    {
        $customer = $this->makeCustomer(0);
        $customer->update(['iban' => 'TR123456789012345678901234', 'iban_holder' => 'Eski']);

        $this->actingAs($customer)->put('/musteri/profil/banka', ['iban' => '', 'iban_holder' => 'Eski'])
            ->assertRedirect();

        $customer->refresh();
        $this->assertNull($customer->iban);
        $this->assertNull($customer->iban_holder); // iban boşsa hesap sahibi de temizlenir
    }
}
