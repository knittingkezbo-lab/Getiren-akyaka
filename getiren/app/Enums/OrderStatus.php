<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Estimated = 'estimated';
    case Reserved = 'reserved';
    case Assigned = 'assigned';
    case Shopping = 'shopping';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case RequiresExtraPayment = 'requires_extra_payment';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Estimated => 'Tahmin edildi',
            self::Reserved => 'Provizyon alındı',
            self::Assigned => 'Kurye atandı',
            self::Shopping => 'Alışverişte',
            self::OnTheWay => 'Yolda',
            self::Delivered => 'Teslim edildi',
            self::RequiresExtraPayment => 'Ek ödeme bekliyor',
            self::Cancelled => 'İptal edildi',
        };
    }

    /** Kuryeye açık, üzerinde çalışılan aktif durumlar */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Reserved, self::Assigned, self::Shopping, self::OnTheWay,
        ], true);
    }
}
