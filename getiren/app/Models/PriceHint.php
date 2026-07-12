<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHint extends Model
{
    protected $fillable = ['keyword', 'category', 'unit_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
