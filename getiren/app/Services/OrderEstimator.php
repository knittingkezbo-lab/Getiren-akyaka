<?php

namespace App\Services;

use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;

class OrderEstimator
{
    /**
     * Serbest metin siparişinden provizyon tutarını çıkarır.
     * "Provizyona al → fişe göre kes → fazlasını iade et" akışının giriş noktası;
     * sunucu tarafında OTORİTER hesaplama (istemci sadece önizleme yapar).
     *
     * Tahminin işi isabet değil YETERLİLİK: fazla tahmin zararsız (fark çözülür),
     * az tahmin müşteriye "ek ödeme" sürtünmesi yaşatır. Bu yüzden tanımadığımız
     * kalem varsa güvenlik payını bilinçli olarak yükseltiriz.
     *
     * @return array{items: array<int, array{name:string, qty:int, estimated_price:float, known:bool}>, items_total: float, safety_buffer: float, service_fee: float, reserved_amount: float, unknown_count: int, buffer_pct: float}
     */
    public function estimate(string $text, Zone $zone): array
    {
        $bufferPct = (float) Setting::get('safety_buffer_pct', 15);
        $unknownBufferPct = (float) Setting::get('unknown_buffer_pct', 35);
        $fallbackPrice = (float) Setting::get('fallback_item_price', 60);
        $minTotal = (float) Setting::get('min_order_total', 0);

        // Uzun anahtar kelimeler önce (ör. "ağrı kesici" > "kesici", "süt" > "su")
        $hints = PriceHint::where('is_active', true)->get()
            ->sortByDesc(fn (PriceHint $h) => mb_strlen($h->keyword))
            ->values();

        $parts = collect(preg_split('/[,\n;]+/u', mb_strtolower($text, 'UTF-8')))
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->values();

        $items = [];
        $itemsTotal = 0.0;
        $unknownCount = 0;

        foreach ($parts as $part) {
            $qty = preg_match('/^(\d+)/', $part, $m) ? max(1, (int) $m[1]) : 1;

            $match = $this->match($hints, $part);

            if ($match === null) {
                $unknownCount++;
                $price = $fallbackPrice;
                $name = mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');
            } else {
                $price = (float) $match->unit_price;
                $name = mb_convert_case($match->keyword, MB_CASE_TITLE, 'UTF-8');
            }

            $lineTotal = $qty * $price;
            $itemsTotal += $lineTotal;

            $items[] = [
                'name' => $name,
                'qty' => $qty,
                'estimated_price' => $lineTotal,
                'known' => $match !== null,
            ];
        }

        $itemsTotal = max($itemsTotal, $minTotal);

        // Tanımadığımız kalem varsa payı yükselt — ek ödeme sürtünmesini azaltır
        $effectiveBufferPct = $unknownCount > 0 ? max($bufferPct, $unknownBufferPct) : $bufferPct;

        $buffer = (float) ceil($itemsTotal * $effectiveBufferPct / 100);
        $fee = (float) $zone->service_fee;

        return [
            'items' => $items,
            'items_total' => $itemsTotal,
            'safety_buffer' => $buffer,
            'service_fee' => $fee,
            'reserved_amount' => $itemsTotal + $buffer + $fee,
            'unknown_count' => $unknownCount,
            'buffer_pct' => $effectiveBufferPct,
        ];
    }

    /** Metin parçasını sözlükle eşleştir (kelime sınırına saygılı); yoksa null. */
    private function match(iterable $hints, string $part): ?PriceHint
    {
        foreach ($hints as $hint) {
            $kw = preg_quote(mb_strtolower($hint->keyword, 'UTF-8'), '/');

            if (preg_match('/(^|[^\p{L}])'.$kw.'([^\p{L}]|$)/u', $part)) {
                return $hint;
            }
        }

        return null;
    }
}
