<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IBAN, paranın gideceği yeri belirleyen tek alan.
 *
 * Eskiden yalnızca /^TR\d{24}$/ biçim kontrolü vardı: tek hanesi yanlış yazılmış bir
 * IBAN bu kontrolden geçiyordu. Gerçek bankacılıkta bunu yakalayan şey mod-97
 * checksum'dır — onsuz para yanlış ya da var olmayan bir hesaba gönderilebilir.
 *
 * Ayrıca IBAN veritabanında düz metin duruyordu.
 */
class IbanProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** Gövdesi aynı, yalnızca kontrol hanesi doğru olan gerçek-biçimli IBAN. */
    private const GECERLI = 'TR330006100519786457841326';

    /** Aynı IBAN'ın son hanesi değiştirilmiş hâli: biçim doğru, checksum yanlış. */
    private const HATALI = 'TR330006100519786457841327';

    public function test_valid_iban_is_accepted(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => self::GECERLI,
            'iban_holder' => 'Gencer Ger',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(self::GECERLI, $customer->fresh()->iban);
    }

    /** ASIL KAPI: biçimi doğru ama checksum'ı tutmayan IBAN reddedilmeli. */
    public function test_iban_with_bad_checksum_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        // Önce ikisinin de eski biçim kontrolünden geçtiğini göster
        $this->assertMatchesRegularExpression('/^TR\d{24}$/', self::HATALI);

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => self::HATALI,
            'iban_holder' => 'Gencer Ger',
        ])->assertSessionHasErrors('iban');

        $this->assertNull($customer->fresh()->iban, 'Checksum hatalı IBAN kaydedildi — para yanlış hesaba gidebilir!');
    }

    /** Boşluklu/küçük harfli giriş normalize edilip yine de doğrulanmalı. */
    public function test_spaced_lowercase_valid_iban_is_normalized(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => 'tr33 0006 1005 1978 6457 8413 26',
            'iban_holder' => 'Gencer Ger',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(self::GECERLI, $customer->fresh()->iban);
    }

    /** IBAN ve hesap sahibi veritabanında düz metin durmamalı. */
    public function test_iban_is_encrypted_at_rest(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer)->put('/musteri/profil/banka', [
            'iban' => self::GECERLI,
            'iban_holder' => 'Gencer Ger',
        ])->assertRedirect();

        $raw = DB::table('users')->where('id', $customer->id)->value('iban');
        $rawHolder = DB::table('users')->where('id', $customer->id)->value('iban_holder');

        $this->assertNotNull($raw);
        $this->assertNotSame(self::GECERLI, $raw, 'IBAN veritabanında düz metin duruyor!');
        $this->assertStringNotContainsString('6457841326', $raw, 'Şifreli değerin içinde IBAN okunuyor!');
        $this->assertStringNotContainsString('Gencer', (string) $rawHolder, 'Hesap sahibi düz metin duruyor!');

        // Uygulama tarafında yine okunabilir olmalı
        $this->assertSame(self::GECERLI, $customer->fresh()->iban);
    }

    /** Ekranda tam IBAN gösterilmemeli: son 4 hane yeter. */
    public function test_masked_iban_hides_the_middle(): void
    {
        $customer = $this->makeCustomer();
        $customer->update(['iban' => self::GECERLI, 'iban_holder' => 'Gencer Ger']);

        $masked = $customer->fresh()->masked_iban;

        $this->assertStringEndsWith('1326', $masked, 'Son 4 hane görünmeli ki kullanıcı doğrulayabilsin');
        $this->assertStringStartsWith('TR', $masked);
        $this->assertStringNotContainsString('0006100519786457', $masked, 'Maskeleme IBAN gövdesini gizlemiyor!');
    }

    public function test_null_iban_has_no_mask(): void
    {
        $this->assertNull($this->makeCustomer()->masked_iban);
    }
}
