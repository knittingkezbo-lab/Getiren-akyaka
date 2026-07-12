<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'reserved', 'currency'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'reserved' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Deftere tek bir hareket yazar ve bakiye/bloke önbelleğini günceller.
     * amount        = kullanılabilir bakiye değişimi (işaretli)
     * reservedDelta = bloke değişimi (işaretli)
     * Tüm bloke/tahsil/iade mantığının tek geçiş noktası.
     */
    public function recordTransaction(
        TransactionType $type,
        float $amount = 0,
        float $reservedDelta = 0,
        ?Order $order = null,
        ?string $note = null,
        array $meta = [],
    ): WalletTransaction {
        $this->balance = round((float) $this->balance + $amount, 2);
        $this->reserved = round((float) $this->reserved + $reservedDelta, 2);
        $this->save();

        return $this->transactions()->create([
            'order_id' => $order?->id,
            'type' => $type,
            'amount' => $amount,
            'reserved_delta' => $reservedDelta,
            'balance_after' => $this->balance,
            'reserved_after' => $this->reserved,
            'note' => $note,
            'meta' => $meta ?: null,
        ]);
    }
}
