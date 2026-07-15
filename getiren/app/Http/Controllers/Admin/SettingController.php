<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
                'min_order_total' => (float) Setting::get('min_order_total', 100),
                'accepting_orders' => (bool) Setting::get('accepting_orders', 1),
                'auto_assign_courier' => (bool) Setting::get('auto_assign_courier', 0),
            ],
            'priceHints' => PriceHint::orderBy('keyword')->get(['id', 'keyword', 'category', 'unit_price']),
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
