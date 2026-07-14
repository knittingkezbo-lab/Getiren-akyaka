<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'code', 'customer_id', 'courier_id', 'zone_id', 'raw_text',
        'address_label', 'address_text', 'customer_note',
        'items_total', 'safety_buffer', 'service_fee', 'reserved_amount',
        'actual_receipt_amount', 'captured_amount', 'refund_amount', 'extra_required_amount',
        'status', 'terms_version', 'reserved_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'items_total' => 'decimal:2',
            'safety_buffer' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'reserved_amount' => 'decimal:2',
            'actual_receipt_amount' => 'decimal:2',
            'captured_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'extra_required_amount' => 'decimal:2',
            'reserved_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(PaymentAuthorization::class);
    }

    /** Hâlâ açık (tahsil ya da çözme bekleyen) provizyon — yoksa null. */
    public function activeAuthorization(): ?PaymentAuthorization
    {
        return $this->authorizations()
            ->where('status', AuthorizationStatus::Authorized)
            ->latest('id')
            ->first();
    }
}
