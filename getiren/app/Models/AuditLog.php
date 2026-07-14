<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Yönetici eylemlerinin kaydı. Yalnızca eklenir — güncellenemez, silinemez.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'actor_name', 'action', 'subject_type', 'subject_id',
        'subject_label', 'description', 'meta', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Denetim kaydının değeri değiştirilemez olmasından gelir; sessizce bozulmasın diye patlıyoruz
        static::updating(fn () => throw new RuntimeException('Denetim kaydı değiştirilemez.'));
        static::deleting(fn () => throw new RuntimeException('Denetim kaydı silinemez.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Denetim kaydı yaz. Aktör ve IP istekten okunur; hedefin etiketi (ad / sipariş no)
     * hedef sonradan silinse bile kayıt okunabilir kalsın diye kopyalanır.
     */
    public static function record(
        AuditAction $action,
        string $description,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        array $meta = [],
    ): self {
        $actor = auth()->user();

        return static::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'sistem',
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'description' => $description,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
