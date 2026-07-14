<?php

namespace App\Payments;

use App\Enums\AuthorizationStatus;
use App\Models\Order;
use App\Models\PaymentAuthorization;
use Illuminate\Support\Str;

/**
 * Demo sürücüsü: gerçek para hareketi yapmaz, sağlayıcı çağrısı yerine kaydı yazar.
 * Durum makinesi gerçek bir PSP ile aynı: açık provizyon yalnızca BİR kez
 * capture ya da void edilebilir — çift tahsil/çift iade bu katmanda imkânsız.
 */
class DemoGateway implements PaymentGateway
{
    public function authorize(Order $order, float $amount, ?string $note = null): PaymentAuthorization
    {
        if ($amount <= 0) {
            throw new PaymentException('Provizyon tutarı sıfırdan büyük olmalı.');
        }

        return PaymentAuthorization::create([
            'order_id' => $order->id,
            'provider' => 'demo',
            'provider_ref' => 'demo_'.Str::lower(Str::random(16)),
            'amount' => round($amount, 2),
            'status' => AuthorizationStatus::Authorized,
            'authorized_at' => now(),
            'note' => $note,
        ]);
    }

    public function capture(PaymentAuthorization $authorization, float $amount): PaymentAuthorization
    {
        $this->assertOpen($authorization);

        // Gerçek PSP'de de provizyondan FAZLASI tahsil edilemez; fazlası ayrı bir çekimdir
        if ($amount < 0 || round($amount, 2) > round((float) $authorization->amount, 2)) {
            throw new PaymentException('Tahsil tutarı provizyonu aşamaz.');
        }

        $authorization->update([
            'status' => AuthorizationStatus::Captured,
            'captured_amount' => round($amount, 2),
            'captured_at' => now(),
        ]);

        return $authorization;
    }

    public function void(PaymentAuthorization $authorization): PaymentAuthorization
    {
        $this->assertOpen($authorization);

        $authorization->update([
            'status' => AuthorizationStatus::Voided,
            'captured_amount' => 0,
            'voided_at' => now(),
        ]);

        return $authorization;
    }

    /** Kapanmış provizyona ikinci kez dokunulamaz — çift-settle hatasının kök çözümü. */
    private function assertOpen(PaymentAuthorization $authorization): void
    {
        if ($authorization->status !== AuthorizationStatus::Authorized) {
            throw new PaymentException(
                "Provizyon '{$authorization->status->label()}' durumunda — üzerinde yeni işlem yapılamaz.",
            );
        }
    }
}
