<?php

namespace App\Payments;

use App\Enums\AuthorizationStatus;
use App\Models\Order;
use App\Models\PaymentAuthorization;

/**
 * PayTR ödeme sağlayıcısı — İSKELET.
 *
 * ⚠️ Bu bir tak-çalıştır çatısıdır; canlıya almadan önce üç şey gerekir:
 *   1) PayTR onayı + mağaza anahtarları (.env: PAYTR_MERCHANT_ID/KEY/SALT).
 *   2) PayTR'de "Ön Provizyon" (blokeli ödeme) yetkisinin açık olması — provizyon
 *      modelimiz buna dayanır. Standart hesap yalnızca anında tahsilat verir.
 *   3) Sipariş akışına YÖNLENDİRME adımının eklenmesi (aşağıya bak).
 *
 * AKIŞ (DemoGateway'den farkı):
 *   Kart bilgisi bize HİÇ gelmez. authorize() PayTR'den bir iframe/token alır ve
 *   provizyonu 'pending' yazar; müşteri PayTR'nin sayfasında kartını girer; PayTR
 *   sonucu callback ile bildirir (PaymentCallbackController) → 'pending' → 'authorized'.
 *   Yani authorize() SENKRON "para bloke edildi" garantisi VERMEZ; onay callback'te gelir.
 *   Bu yüzden OrderController::store, paytr sürücüsündeyken kullanıcıyı PayTR'ye
 *   yönlendirmeli. Anahtarlar gelince bu adımı ekleyip uçtan uca test edeceğiz.
 *
 * Endpoint/parametre adları PayTR mağaza dokümanına göre KESİNLEŞTİRİLMELİ
 * (aşağıdaki // DOĞRULA: notları). Hash üretimi PayTR'nin standart HMAC-SHA256
 * (base64) desenini izler.
 */
class PayTRGateway implements PaymentGateway
{
    public function authorize(Order $order, float $amount, ?string $note = null): PaymentAuthorization
    {
        $this->assertConfigured();

        if ($amount <= 0) {
            throw new PaymentException('Provizyon tutarı sıfırdan büyük olmalı.');
        }

        $cfg = config('payments.paytr');
        $merchantOid = 'GA'.$order->id.'T'.now()->timestamp; // PayTR'ye giden benzersiz sipariş no
        $amountKurus = (int) round($amount * 100);            // PayTR tutarları kuruş cinsinden ister

        // Önce kaydı 'pending' aç — callback bu satırı 'authorized'a çevirecek
        $authorization = PaymentAuthorization::create([
            'order_id' => $order->id,
            'provider' => 'paytr',
            'provider_ref' => $merchantOid,
            'amount' => round($amount, 2),
            'status' => AuthorizationStatus::Pending,
            'authorized_at' => null,
            'note' => $note,
        ]);

        // DOĞRULA: PayTR "get-token" (iframe) çağrısı — Ön Provizyon için ilgili
        // parametre (ör. islem_tipi/pre-auth) PayTR dokümanına göre eklenmeli.
        // response = Http::asForm()->post($cfg['base_url'].'/odeme/api/get-token', [...]);
        // $authorization->update(['meta' => ['paytr_token' => $response['token']]]);
        //
        // Denemeden önce iskelet bilinçli olarak durur:
        throw new PaymentException(
            'PayTR entegrasyonu henüz tamamlanmadı: iframe token çağrısı ve Ön Provizyon '.
            'parametreleri PayTR dokümanı + anahtarlarla eklenecek. (merchant_oid: '.$merchantOid.')',
        );
    }

    public function capture(PaymentAuthorization $authorization, float $amount): PaymentAuthorization
    {
        $this->assertConfigured();
        $this->assertAuthorized($authorization);

        if ($amount < 0 || round($amount, 2) > round((float) $authorization->amount, 2)) {
            throw new PaymentException('Tahsil tutarı provizyonu aşamaz.');
        }

        // DOĞRULA: PayTR "provizyon kapatma" (post-auth) endpoint'i + hash.
        // Kısmi kapatmada kalan tutar PayTR'de otomatik çözülür ("fazlasını iade et").
        // Http::asForm()->post($this->cfg('base_url').'/odeme/...', [...]);

        throw new PaymentException('PayTR provizyon kapatma (capture) çağrısı henüz bağlanmadı.');
    }

    public function void(PaymentAuthorization $authorization): PaymentAuthorization
    {
        $this->assertConfigured();
        $this->assertAuthorized($authorization);

        // DOĞRULA: PayTR "provizyon iptal / iade" endpoint'i + hash.
        // Http::asForm()->post($this->cfg('base_url').'/odeme/...', [...]);

        throw new PaymentException('PayTR provizyon iptal (void) çağrısı henüz bağlanmadı.');
    }

    /** PayTR token hash'i: HMAC-SHA256(base64) — merchant_key ile imzalanır, salt eklenir. */
    public function token(string $payload): string
    {
        $cfg = config('payments.paytr');

        return base64_encode(hash_hmac('sha256', $payload.$cfg['merchant_salt'], $cfg['merchant_key'], true));
    }

    private function cfg(string $key): mixed
    {
        return config("payments.paytr.{$key}");
    }

    private function assertConfigured(): void
    {
        $cfg = config('payments.paytr');

        if (blank($cfg['merchant_id']) || blank($cfg['merchant_key']) || blank($cfg['merchant_salt'])) {
            throw new PaymentException('PayTR anahtarları tanımlı değil (.env: PAYTR_MERCHANT_ID/KEY/SALT).');
        }
    }

    private function assertAuthorized(PaymentAuthorization $authorization): void
    {
        if ($authorization->status !== AuthorizationStatus::Authorized) {
            throw new PaymentException(
                "Provizyon '{$authorization->status->label()}' durumunda — üzerinde yeni işlem yapılamaz.",
            );
        }
    }
}
