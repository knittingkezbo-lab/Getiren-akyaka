<?php

namespace App\Enums;

/**
 * Bir fiyatın nereden geldiği. Güven sırası (yüksekten düşüğe):
 * Observed → Manual → Reference → Fallback.
 */
enum PriceSource: string
{
    case Observed = 'observed';   // kuryenin girdiği GERÇEK yerel fiyat (fiş/elle) — en güvenilir
    case Manual = 'manual';       // admin'in panelden girdiği fiyat
    case Reference = 'reference'; // dış/zincir referansı (yalnızca iç kullanım, müşteriye gösterilmez)
    case Fallback = 'fallback';   // hiç bilgi yok — kategori/genel varsayılan

    public function label(): string
    {
        return match ($this) {
            self::Observed => 'Gerçek fiyat (kurye girdi)',
            self::Manual => 'Elle girildi',
            self::Reference => 'Referans (dış kaynak)',
            self::Fallback => 'Varsayılan (tahmin)',
        };
    }

    /** Rozet rengi (badge--*). */
    public function tone(): string
    {
        return match ($this) {
            self::Observed => 'sage',
            self::Manual => 'primary',
            self::Reference => 'amber',
            self::Fallback => 'muted',
        };
    }

    /** Gözlenen fiyat, elle girileni; o da referansı ezer. Küçük = daha güvenilir. */
    public function trust(): int
    {
        return match ($this) {
            self::Observed => 0,
            self::Manual => 1,
            self::Reference => 2,
            self::Fallback => 3,
        };
    }
}
