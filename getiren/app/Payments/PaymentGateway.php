<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\PaymentAuthorization;

/**
 * Ödeme sağlayıcısı sözleşmesi.
 *
 * Getiren para TUTMAZ; her sipariş için müşterinin ödeme aracında provizyon açar,
 * gerçek fiş gelince o provizyondan keser, kalanı çözer. Uygulama katmanı yalnızca
 * bu üç adımı bilir — hangi sağlayıcının kullanıldığını bilmez.
 */
interface PaymentGateway
{
    /**
     * Provizyon al: tutarı müşterinin ödeme aracında bloke eder.
     * Para hesaba GEÇMEZ, yalnızca ayrılır.
     *
     * @throws PaymentException sağlayıcı reddederse
     */
    public function authorize(Order $order, float $amount, ?string $note = null): PaymentAuthorization;

    /**
     * Provizyonun tamamını veya bir kısmını tahsil et.
     * Kısmi tahsilde kalan tutar sağlayıcı tarafından çözülür — "fazlasını iade et" budur.
     *
     * @throws PaymentException tutar provizyonu aşarsa veya provizyon açık değilse
     */
    public function capture(PaymentAuthorization $authorization, float $amount): PaymentAuthorization;

    /**
     * Provizyonu hiç tahsil etmeden tamamen çöz (sipariş iptali).
     *
     * @throws PaymentException provizyon açık değilse
     */
    public function void(PaymentAuthorization $authorization): PaymentAuthorization;
}
