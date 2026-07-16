<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\PriceSource;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;
use App\Support\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /** Denetim kaydında okunur görünsün diye ayar anahtarlarının Türkçe adları. */
    private const SETTING_LABELS = [
        'safety_buffer_pct' => 'Güvenlik payı (%)',
        'unknown_buffer_pct' => 'Bilinmeyen kalem payı (%)',
        'fallback_item_price' => 'Bilinmeyen kalem varsayılan fiyatı',
        'min_order_total' => 'Minimum sipariş tutarı',
        'accepting_orders' => 'Sipariş kabulü',
        'auto_assign_courier' => 'Otomatik kurye atama',
    ];

    /** Şirket bilgisi alanlarının Türkçe adları (denetim kaydı için). */
    private const COMPANY_LABELS = [
        'name' => 'Marka adı',
        'legal_name' => 'İşletme unvanı',
        'owner' => 'İşletme sahibi',
        'type' => 'İşletme türü',
        'tax_office' => 'Vergi dairesi',
        'tax_no' => 'Vergi/TC no',
        'mersis' => 'MERSİS',
        'etbis' => 'ETBİS',
        'nace' => 'NACE',
        'address' => 'Adres',
        'phone' => 'Telefon',
        'email' => 'E-posta',
        'kep' => 'KEP',
        'website' => 'Web',
        'hours' => 'Çalışma saatleri',
        'service_areas' => 'Hizmet bölgeleri',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'zones' => Zone::orderBy('sort_order')->get(['id', 'name', 'service_fee', 'is_active']),
            'settings' => [
                'safety_buffer_pct' => (float) Setting::get('safety_buffer_pct', 15),
                'unknown_buffer_pct' => (float) Setting::get('unknown_buffer_pct', 35),
                'fallback_item_price' => (float) Setting::get('fallback_item_price', 60),
                'min_order_total' => (float) Setting::get('min_order_total', 100),
                'accepting_orders' => (bool) Setting::get('accepting_orders', 1),
                'auto_assign_courier' => (bool) Setting::get('auto_assign_courier', 0),
            ],
            'priceHints' => PriceHint::orderBy('keyword')->get()->map(fn (PriceHint $p) => [
                'id' => $p->id,
                'keyword' => $p->keyword,
                'category' => $p->category,
                'unit_price' => (float) $p->unit_price,
                'source' => $p->source->value,
                'source_label' => $p->source->label(),
                'tone' => $p->source->tone(),
                'observed_count' => $p->observed_count,
                'needs_review' => $p->isStale(),
            ]),
            'estimateStats' => $this->estimateStats(),
            'company' => Company::all(),
            'legalDraft' => Company::draft(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zones' => ['array'],
            'zones.*.id' => ['required', 'integer', 'exists:zones,id'],
            'zones.*.service_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'zones.*.is_active' => ['required', 'boolean'],
            'settings.safety_buffer_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings.unknown_buffer_pct' => ['required', 'numeric', 'min:0', 'max:200'],
            'settings.fallback_item_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'settings.min_order_total' => ['required', 'numeric', 'min:0'],
            'settings.accepting_orders' => ['required', 'boolean'],
            'settings.auto_assign_courier' => ['required', 'boolean'],
            'priceHints' => ['array'],
            'priceHints.*.id' => ['required', 'integer', 'exists:price_hints,id'],
            'priceHints.*.unit_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'company' => ['array'],
            'company.*' => ['nullable', 'string', 'max:255'],
            'legal_draft' => ['boolean'],
        ]);

        DB::transaction(function () use ($data) {
            $changes = [];

            foreach ($data['zones'] ?? [] as $row) {
                $zone = Zone::find($row['id']);

                $this->diff($changes, "Bölge · {$zone->name} · hizmet bedeli", (float) $zone->service_fee, (float) $row['service_fee']);
                $this->diff($changes, "Bölge · {$zone->name} · aktif", (bool) $zone->is_active, (bool) $row['is_active']);

                $zone->update(['service_fee' => $row['service_fee'], 'is_active' => $row['is_active']]);
            }

            foreach ($data['priceHints'] ?? [] as $row) {
                $hint = PriceHint::find($row['id']);

                $this->diff($changes, "Fiyat ipucu · {$hint->keyword}", (float) $hint->unit_price, (float) $row['unit_price']);

                $hint->update(['unit_price' => $row['unit_price']]);
            }

            foreach (self::SETTING_LABELS as $key => $label) {
                $value = $data['settings'][$key];
                $value = is_bool($value) ? (int) $value : $value;

                $this->diff($changes, $label, Setting::get($key), $value);

                Setting::put($key, $value);
            }

            // Şirket bilgisi: yalnızca değişen alanı DB'ye yaz (yoksa env yedeği geçerli kalır)
            foreach (self::COMPANY_LABELS as $key => $label) {
                if (! array_key_exists($key, $data['company'] ?? [])) {
                    continue;
                }

                $new = trim((string) ($data['company'][$key] ?? ''));

                if ($this->diff($changes, "Şirket · {$label}", Company::get($key), $new)) {
                    Setting::put("company_{$key}", $new);
                }
            }

            // Hukuki metin taslak bandı (avukat bitince kapatılır)
            if (array_key_exists('legal_draft', $data)) {
                $newDraft = (bool) $data['legal_draft'];

                if ($this->diff($changes, 'Hukuki metin taslak bandı', Company::draft(), $newDraft)) {
                    Setting::put('company_legal_draft', $newDraft ? 1 : 0);
                }
            }

            // Değişen bir şey yoksa kayıt açma — boş "Kaydet" tıklamaları denetim kaydını kirletmesin
            if ($changes !== []) {
                AuditLog::record(
                    AuditAction::SettingsUpdated,
                    count($changes) === 1
                        ? array_key_first($changes).' değiştirildi.'
                        : count($changes).' ayar değiştirildi.',
                    meta: ['changes' => $changes],
                );
            }
        });

        return back()->with('success', 'Ayarlar kaydedildi.');
    }

    /**
     * Tahmin isabeti — güvenlik payını tahminle değil VERİYLE ayarlamak için.
     * Kıyas: tahmini ürün tutarı (items_total) vs gerçek fiş (actual_receipt_amount).
     * Pozitif sapma = fazla tahmin (güvenli), negatif = az tahmin (ek ödeme riski).
     */
    private function estimateStats(): array
    {
        $orders = Order::whereNotNull('actual_receipt_amount')
            ->where('actual_receipt_amount', '>', 0)
            ->latest('id')
            ->take(50)
            ->get(['items_total', 'actual_receipt_amount', 'extra_required_amount']);

        if ($orders->isEmpty()) {
            return ['count' => 0];
        }

        $deviations = $orders->map(fn (Order $o) => (((float) $o->items_total - (float) $o->actual_receipt_amount)
            / (float) $o->actual_receipt_amount) * 100);

        $extraCount = $orders->filter(fn (Order $o) => (float) ($o->extra_required_amount ?? 0) > 0)->count();

        return [
            'count' => $orders->count(),
            'avg_deviation' => round((float) $deviations->avg(), 1),
            'under_rate' => round($deviations->filter(fn ($d) => $d < 0)->count() / $orders->count() * 100, 1),
            'extra_rate' => round($extraCount / $orders->count() * 100, 1),
        ];
    }

    /**
     * Toplu fiyat içe aktarma — mağaza gezmeden hızlı soğuk başlangıç.
     * Satır formatı: "kelime; kategori; fiyat" veya "kelime; fiyat".
     * Gözlenen (gerçek) fiyatları EZMEZ — onlar sahadan gelen gerçektir.
     */
    public function importPrices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:100000'],
        ]);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $protected = 0;

        foreach (preg_split('/\r\n|\r|\n/', $data['text']) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $cols = $this->splitImportLine($line);

            if ($cols === null) {
                $skipped++;

                continue;
            }

            [$keyword, $category, $price] = $cols;

            $hint = PriceHint::whereRaw('LOWER(keyword) = ?', [$keyword])->first();

            if ($hint === null) {
                PriceHint::create([
                    'keyword' => $keyword,
                    'category' => $category,
                    'unit_price' => $price,
                    'is_active' => true,
                    'source' => PriceSource::Manual,
                ]);
                $created++;

                continue;
            }

            // Sahadan gelen gerçek fiyat, elle içe aktarılan tahminden üstündür
            if ($hint->source === PriceSource::Observed) {
                $protected++;

                continue;
            }

            $hint->update([
                'unit_price' => $price,
                'category' => $category ?: $hint->category,
                'source' => PriceSource::Manual,
            ]);
            $updated++;
        }

        if ($created + $updated > 0) {
            AuditLog::record(
                AuditAction::SettingsUpdated,
                "Fiyat listesi içe aktarıldı: {$created} yeni, {$updated} güncellendi.",
                meta: ['changes' => ['Fiyat içe aktarma' => [
                    'eski' => '—',
                    'yeni' => "{$created} yeni · {$updated} güncel · {$protected} korundu (gerçek fiyat) · {$skipped} atlandı",
                ]]],
            );
        }

        return back()->with('success', "İçe aktarıldı: {$created} yeni, {$updated} güncellendi, {$protected} gerçek fiyat korundu, {$skipped} satır atlandı.");
    }

    /** "kelime; kategori; fiyat" → [keyword, category, price]; ayrıştırılamazsa null. */
    private function splitImportLine(string $line): ?array
    {
        $parts = preg_match('/[;\t]/', $line)
            ? preg_split('/[;\t]/', $line)
            : (substr_count($line, ',') === 1 ? explode(',', $line) : null);

        if ($parts === null) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        if (count($parts) < 2 || count($parts) > 3) {
            return null;
        }

        $keyword = mb_strtolower($parts[0], 'UTF-8');
        $category = count($parts) === 3 ? $parts[1] : null;
        $raw = str_replace([' ', 'TL', 'tl', '₺'], '', (string) end($parts));
        $price = (float) str_replace(',', '.', $raw);

        if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 30 || $price <= 0) {
            return null;
        }

        return [$keyword, $category, round($price, 2)];
    }

    /** Değer gerçekten değiştiyse eski→yeni farkını biriktir. Değişti mi döndürür. */
    private function diff(array &$changes, string $label, mixed $old, mixed $new): bool
    {
        $normalize = fn (mixed $v) => is_bool($v) ? (string) (int) $v : (string) $v;

        if ($normalize($old) === $normalize($new)) {
            return false;
        }

        $changes[$label] = ['eski' => $old, 'yeni' => $new];

        return true;
    }
}
