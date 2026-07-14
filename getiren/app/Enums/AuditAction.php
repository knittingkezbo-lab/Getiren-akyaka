<?php

namespace App\Enums;

enum AuditAction: string
{
    case CourierApproved = 'courier.approved';
    case CourierRejected = 'courier.rejected';
    case OrderAssigned = 'order.assigned';
    case SettingsUpdated = 'settings.updated';

    public function label(): string
    {
        return match ($this) {
            self::CourierApproved => 'Kurye onaylandı',
            self::CourierRejected => 'Kurye reddedildi',
            self::OrderAssigned => 'Sipariş atandı',
            self::SettingsUpdated => 'Ayarlar değiştirildi',
        };
    }

    /** Rozet rengi (badge--*). */
    public function tone(): string
    {
        return match ($this) {
            self::CourierApproved => 'sage',
            self::CourierRejected => 'danger',
            self::OrderAssigned => 'primary',
            self::SettingsUpdated => 'amber',
        };
    }
}
