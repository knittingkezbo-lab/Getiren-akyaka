<?php

namespace App\Enums;

enum TransactionType: string
{
    case TopUp = 'topup';          // cüzdana yükleme
    case Hold = 'hold';            // sipariş onayında bloke
    case Release = 'release';      // blokenin iptalle geri açılması
    case Capture = 'capture';      // fişe göre tahsil (blokeden düşer)
    case Refund = 'refund';        // fazlanın iadesi
    case ExtraCharge = 'extra_charge'; // fiş blokeyi aşınca ek tahsil
    case Adjustment = 'adjustment';    // manuel düzeltme

    public function label(): string
    {
        return match ($this) {
            self::TopUp => 'Demo bakiye ekleme',
            self::Hold => 'Provizyon',
            self::Release => 'Provizyon çözüldü',
            self::Capture => 'Tahsil',
            self::Refund => 'İade',
            self::ExtraCharge => 'Ek ödeme',
            self::Adjustment => 'Düzeltme',
        };
    }
}
