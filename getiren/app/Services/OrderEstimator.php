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

        $text = preg_replace('/\s+ve\s+/u', "\n", mb_strtolower($text, 'UTF-8'));

        $parts = collect(preg_split('/[,\n;]+/u', $text))
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->values();

        $items = [];
        $itemsTotal = 0.0;
        $unknownCount = 0;

        foreach ($parts as $part) {
            $qty = $this->quantity($part);

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
        $partTokens = $this->tokens($part, dropLeadingQuantity: true);

        foreach ($hints as $hint) {
            $hintTokens = $this->tokens($hint->keyword);

            if ($this->isConfidentMatch($hintTokens, $partTokens)) {
                return $hint;
            }
        }

        return null;
    }

    /**
     * Tek kelimelik sözlük girdileri risklidir: "kahve" ≠ "kahve makinesi",
     * "peynir" ≠ "beyaz peynir 600gr". Emin değilsek unknown kalsın; fazla
     * buffer, alakasız fiyatla eksik provizyondan daha güvenli.
     */
    private function isConfidentMatch(array $hintTokens, array $partTokens): bool
    {
        if ($hintTokens === [] || count(array_diff($hintTokens, $partTokens)) !== 0) {
            return false;
        }

        if (count($hintTokens) > 1) {
            return true;
        }

        $extra = array_values(array_diff($partTokens, $hintTokens));
        if ($extra === []) {
            return true;
        }

        $allowedContainerWords = ['adet', 'kutu', 'paket', 'şişe', 'sise'];

        foreach ($extra as $token) {
            if (! in_array($token, $allowedContainerWords, true)) {
                return false;
            }
        }

        return true;
    }

    /** Müşteri metninde adet başta da sonda da gelebilir: "2 ekmek", "yumurtadan 2 tane". */
    private function quantity(string $part): int
    {
        if (preg_match('/^\s*(\d+)/u', $part, $m)) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/(?:^|\s)(\d+)\s*(?:adet|tane|paket)\b/u', $part, $m)) {
            return max(1, (int) $m[1]);
        }

        return 1;
    }

    /** Eşleşme için serbest metni daha toleranslı ama hâlâ deterministik token'lara çevir. */
    private function tokens(string $text, bool $dropLeadingQuantity = false): array
    {
        $normalized = $this->normalizeForMatch($text);

        if ($dropLeadingQuantity) {
            $normalized = preg_replace('/^\s*\d+(?:[\.,]\d+)?\s*/u', '', $normalized);
        }

        preg_match_all('/\d+(?:[\.,]\d+)?(?:li|lı|lu|lü)?|\p{L}+/u', $normalized, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function normalizeForMatch(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(['’', '‘', '`', '´'], "'", $text);

        // Paket/birim yazım farkları: "5kg" ≈ "5 kg", "1 lt" ≈ "1lt".
        $text = preg_replace('/(\d+(?:[\.,]\d+)?)\s*(kg|gr|g|ml|lt|l)(?!\p{L})/u', '$1 $2', $text);
        $text = preg_replace('/\b(?:kilogram|kilo)\b/u', 'kg', $text);
        $text = preg_replace('/\b(?:litre|l)\b/u', 'lt', $text);

        // "30ludan", "12'li" gibi ekli paket ifadelerini anahtar token'a indir.
        $text = preg_replace('/(\d+)\s*[\'’]?\s*(li|lı|lu|lü)(?:dan|den|tan|ten)?\b/u', '$1$2', $text);

        // Akyaka müşterisinin doğal dili: sözlük resmî ürün diliyle gelmiş olabilir.
        $text = preg_replace('/\btavuk\b/u', 'piliç', $text);
        $text = preg_replace('/\b(?:göğsü|göğüs|gögsü|gogsu|gogus)\b/u', 'bonfile', $text);

        return $text;
    }
}
