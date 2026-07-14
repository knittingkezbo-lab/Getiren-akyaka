<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;
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

    /** Değer gerçekten değiştiyse eski→yeni farkını biriktir. */
    private function diff(array &$changes, string $label, mixed $old, mixed $new): void
    {
        $normalize = fn (mixed $v) => is_bool($v) ? (string) (int) $v : (string) $v;

        if ($normalize($old) === $normalize($new)) {
            return;
        }

        $changes[$label] = ['eski' => $old, 'yeni' => $new];
    }
}
