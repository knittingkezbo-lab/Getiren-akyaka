<?php

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Authorized = 'authorized';  // provizyon alındı, para ayrıldı ama hesaba geçmedi
    case Captured = 'captured';      // fişe göre kesildi (kalan varsa sağlayıcıda çözüldü)
    case Voided = 'voided';          // hiç tahsil edilmeden tamamen çözüldü
    case Failed = 'failed';          // sağlayıcı reddetti

    public function label(): string
    {
        return match ($this) {
            self::Authorized => 'Provizyon alındı',
            self::Captured => 'Tahsil edildi',
            self::Voided => 'Provizyon çözüldü',
            self::Failed => 'Reddedildi',
        };
    }

    /** Rozet rengi (badge--*). */
    public function tone(): string
    {
        return match ($this) {
            self::Authorized => 'amber',
            self::Captured => 'sage',
            self::Voided => 'muted',
            self::Failed => 'danger',
        };
    }
}
