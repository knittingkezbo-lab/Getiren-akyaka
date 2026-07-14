<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir siparişin ödeme sağlayıcısındaki provizyon kaydı.
 * Bir siparişin birden çok provizyonu olabilir (fiş provizyonu aşarsa fark ayrı çekilir).
 */
class PaymentAuthorization extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'provider_ref', 'status', 'amount', 'captured_amount',
        'authorized_at', 'captured_at', 'voided_at', 'note', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => AuthorizationStatus::class,
            'amount' => 'decimal:2',
            'captured_amount' => 'decimal:2',
            'authorized_at' => 'datetime',
            'captured_at' => 'datetime',
            'voided_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Müşteriye geri bırakılan tutar: tahsil edilmeyen provizyon "iade"dir. */
    public function releasedAmount(): float
    {
        return match ($this->status) {
            AuthorizationStatus::Captured => round((float) $this->amount - (float) $this->captured_amount, 2),
            AuthorizationStatus::Voided => round((float) $this->amount, 2),
            default => 0.0,
        };
    }
}
