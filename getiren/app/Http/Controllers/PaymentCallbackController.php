<?php

namespace App\Http\Controllers;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use App\Models\PaymentAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PayTR ödeme sonucu callback'i (webhook) — İSKELET.
 *
 * PayTR, müşteri kartını girip ödemeyi tamamladığında bu adrese sunucudan sunucuya
 * POST atar. Doğrulama HMAC hash iledir; bize düşen: hash'i doğrula, provizyonu
 * 'pending' → 'authorized' (veya 'failed') yap ve PayTR'ye düz metin "OK" dön.
 *
 * ⚠️ Tamamlanmadı: hash doğrulaması ve alan adları PayTR dokümanına göre bağlanacak.
 * Bu rota CSRF'ten muaf olmalı (dış POST) — VerifyCsrfToken hariç tutulacak.
 */
class PaymentCallbackController extends Controller
{
    public function paytr(Request $request)
    {
        // Entegrasyon (imza doğrulama) bitene kadar yol kapalı — açıkken bile
        // hash doğrulanmadan hiçbir şey yapılmamalı (aşağıdaki DOĞRULA notu).
        abort_unless(config('payments.callback_enabled'), 404);

        $cfg = config('payments.paytr');
        $merchantOid = (string) $request->input('merchant_oid');
        $status = (string) $request->input('status');       // 'success' | 'failed'
        $paytrHash = (string) $request->input('hash');

        // DOĞRULA: PayTR hash'i — beklenen = base64(hmac_sha256(merchant_oid+salt+status+total_amount, key))
        // $expected = base64_encode(hash_hmac('sha256', $merchantOid.$cfg['merchant_salt'].$status.$request->input('total_amount'), $cfg['merchant_key'], true));
        // abort_unless(hash_equals($expected, $paytrHash), 400, 'Geçersiz PayTR hash');

        $authorization = PaymentAuthorization::where('provider', 'paytr')
            ->where('provider_ref', $merchantOid)
            ->first();

        // PayTR aynı bildirimi tekrarlayabilir; yoksa ya da zaten işlenmişse yine "OK" dön
        if (! $authorization || $authorization->status !== AuthorizationStatus::Pending) {
            return response('OK');
        }

        DB::transaction(function () use ($authorization, $status) {
            if ($status === 'success') {
                $authorization->update([
                    'status' => AuthorizationStatus::Authorized,
                    'authorized_at' => now(),
                ]);
                // Sipariş provizyonu kesinleşti → 'reserved' olarak işaretlenebilir
                $authorization->order?->update(['status' => OrderStatus::Reserved, 'reserved_at' => now()]);
            } else {
                $authorization->update(['status' => AuthorizationStatus::Failed]);
                // Ödeme alınamadı → sipariş iptale düşer (müşteri tekrar deneyebilir)
                $authorization->order?->update(['status' => OrderStatus::Cancelled]);
            }
        });

        // PayTR düz metin "OK" bekler; aksi halde bildirimi yeniden dener
        return response('OK');
    }
}
