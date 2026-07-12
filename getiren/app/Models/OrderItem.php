<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'name', 'qty', 'estimated_price', 'actual_price'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'estimated_price' => 'decimal:2',
            'actual_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
