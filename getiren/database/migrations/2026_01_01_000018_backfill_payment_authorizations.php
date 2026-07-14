<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cüzdan döneminden kalan siparişlere provizyon kaydı üretir.
 * Aksi hâlde açık siparişlerin provizyonu olmaz ve iptal/settle 422 ile reddedilir.
 *
 * Yeni kurulumda sipariş yoktur → hiçbir şey yapmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')
            ->whereNotIn('id', DB::table('payment_authorizations')->select('order_id'))
            ->where('reserved_amount', '>', 0)
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $reserved = (float) $order->reserved_amount;
            $captured = (float) ($order->captured_amount ?? 0);

            // Provizyon HER ZAMAN ayrılan tutar kadardır; kesilen ondan az olabilir (aradaki fark iade).
            $rows[] = match ($order->status) {
                'delivered' => $this->row($order, 1, $reserved, 'captured', min($reserved, $captured)),
                'cancelled' => $this->row($order, 1, $reserved, 'voided', 0),
                default => $this->row($order, 1, $reserved, 'authorized', null),
            };

            // Ek ödemeli teslim: fiş provizyonu aşmış, fark ayrı çekim olarak alınmıştı
            if ($order->status === 'delivered' && $captured > $reserved) {
                $rows[] = $this->row($order, 2, $captured - $reserved, 'captured', $captured - $reserved);
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('payment_authorizations')->insert($chunk);
        }
    }

    public function down(): void
    {
        DB::table('payment_authorizations')->where('provider_ref', 'like', 'backfill\_%')->delete();
    }

    private function row(object $order, int $seq, float $amount, string $status, ?float $captured): array
    {
        return [
            'order_id' => $order->id,
            'provider' => 'demo',
            'provider_ref' => "backfill_{$order->id}_{$seq}",
            'status' => $status,
            'amount' => round($amount, 2),
            'captured_amount' => $captured !== null ? round($captured, 2) : null,
            'authorized_at' => $order->reserved_at ?? $order->created_at,
            'captured_at' => $status === 'captured' ? ($order->delivered_at ?? $order->updated_at) : null,
            'voided_at' => $status === 'voided' ? $order->updated_at : null,
            'note' => $seq === 1 ? 'Sipariş provizyonu (geçmişten aktarıldı)' : 'Ek ödeme (geçmişten aktarıldı)',
            'meta' => null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
};
