<?php

namespace App\Models;

use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceHint extends Model
{
    /**
     * Sonraki gözlemlerin ağırlığı (üstel hareketli ortalama).
     * Yüksek = gerçeğe hızlı uyum (sezonluk iş), düşük = daha kararlı.
     */
    private const SMOOTHING = 0.4;

    /** Bu süre boyunca gerçek fiyat görülmemişse "eskimiş" say (haftalık kontrol için). */
    public const STALE_DAYS = 30;

    protected $fillable = [
        'keyword', 'category', 'unit_price', 'is_active',
        'source', 'observed_count', 'last_observed_at', 'reference_price', 'reference_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'reference_price' => 'decimal:2',
            'is_active' => 'boolean',
            'source' => PriceSource::class,
            'observed_count' => 'integer',
            'last_observed_at' => 'datetime',
            'reference_updated_at' => 'datetime',
        ];
    }

    /**
     * Kuryenin girdiği GERÇEK birim fiyatı işle.
     *
     * İlk gerçek gözlem, tahmini/referansı DOĞRUDAN ezer — sezonluk işte hızlı
     * yakınsama için kritik. Sonraki gözlemler üstel ortalamayla yumuşatılır ki
     * tek bir uç fiyat (kampanya, yanlış giriş) sözlüğü bozmasın.
     */
    public function recordObservation(float $unitPrice): void
    {
        if ($unitPrice <= 0) {
            return;
        }

        $alreadyObserved = $this->source === PriceSource::Observed && $this->observed_count > 0;

        $next = $alreadyObserved
            ? (float) $this->unit_price * (1 - self::SMOOTHING) + $unitPrice * self::SMOOTHING
            : $unitPrice;

        $this->update([
            'unit_price' => round($next, 2),
            'source' => PriceSource::Observed,
            'observed_count' => $this->observed_count + 1,
            'last_observed_at' => now(),
        ]);
    }

    /** Gerçek fiyatı uzun süredir görülmemiş mi (haftalık gözden geçirme listesi). */
    public function isStale(): bool
    {
        if ($this->source === PriceSource::Observed) {
            return $this->last_observed_at?->lt(now()->subDays(self::STALE_DAYS)) ?? true;
        }

        // Hiç gerçek fiyat görülmediyse zaten gözden geçirilmeli
        return true;
    }

    /** Henüz gerçek fiyatı görülmemiş ya da bayatlamış kalemler. */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('source', '!=', PriceSource::Observed)
                ->orWhereNull('last_observed_at')
                ->orWhere('last_observed_at', '<', now()->subDays(self::STALE_DAYS));
        });
    }
}
