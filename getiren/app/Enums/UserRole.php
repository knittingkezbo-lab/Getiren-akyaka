<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Courier = 'courier';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Müşteri',
            self::Courier => 'Kurye',
            self::Admin => 'Yönetici',
        };
    }

    /** Giriş sonrası bu rolün açılış rotası. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Customer => 'customer.dashboard',
            self::Courier => 'courier.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }
}
