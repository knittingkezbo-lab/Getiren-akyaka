<?php

namespace App\Services;

use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;

class OrderEstimator
{
    /**
     * Serbest metin siparişinden tahmini bloke tutarını çıkarır.
     * "Bloke et → fişe göre kes → farkı iade et" akışının giriş noktası;
     * sunucu tarafında OTORİTER hesaplama (istemci sadece önizleme yapar).
     *
     * @return array{items: array<int, array{name:string, qty:int, estimated_price:float}>, items_total: float, safety_buffer: float, service_fee: float, reserved_amount: float}
     */
    public function estimate(string $text, Zone $zone): array
    {
        $bufferPct = (float) Setting::get('safety_buffer_pct', 15);
        $minTotal = (float) Setting::get('min_order_total', 0);

        // Uzun anahtar kelimeler önce (ör. "süt" > "su", "ağrı kesici" en başta)
        $hints = PriceHint::where('is_active', true)->get()
            ->sortByDesc(fn (PriceHint $h) => mb_strlen($h->keyword))
            ->values();

        $parts = collect(preg_split('/[,\n;]+/u', mb_strtolower($text, 'UTF-8')))
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->values();

        $items = [];
        $itemsTotal = 0.0;

        foreach ($parts as $part) {
            $qty = preg_match('/^(\d+)/', $part, $m) ? max(1, (int) $m[1]) : 1;

            $price = 40.0;      // bilinmeyen kalem varsayılanı
            $name = mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');

            foreach ($hints as $hint) {
                $kw = preg_quote(mb_strtolower($hint->keyword, 'UTF-8'), '/');
                if (preg_match('/(^|[^\p{L}])'.$kw.'([^\p{L}]|$)/u', $part)) {
                    $price = (float) $hint->unit_price;
                    $name = mb_convert_case($hint->keyword, MB_CASE_TITLE, 'UTF-8');
                    break;
                }
            }

            $lineTotal = $qty * $price;
            $itemsTotal += $lineTotal;
            $items[] = ['name' => $name, 'qty' => $qty, 'estimated_price' => $lineTotal];
        }

        $itemsTotal = max($itemsTotal, $minTotal);
        $buffer = (float) ceil($itemsTotal * $bufferPct / 100);
        $fee = (float) $zone->service_fee;

        return [
            'items' => $items,
            'items_total' => $itemsTotal,
            'safety_buffer' => $buffer,
            'service_fee' => $fee,
            'reserved_amount' => $itemsTotal + $buffer + $fee,
        ];
    }
}
