<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
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

        foreach ($data['zones'] ?? [] as $z) {
            Zone::whereKey($z['id'])->update([
                'service_fee' => $z['service_fee'],
                'is_active' => $z['is_active'],
            ]);
        }

        foreach ($data['priceHints'] ?? [] as $p) {
            PriceHint::whereKey($p['id'])->update(['unit_price' => $p['unit_price']]);
        }

        Setting::put('safety_buffer_pct', $data['settings']['safety_buffer_pct']);
        Setting::put('min_order_total', $data['settings']['min_order_total']);
        Setting::put('accepting_orders', $data['settings']['accepting_orders'] ? 1 : 0);
        Setting::put('auto_assign_courier', $data['settings']['auto_assign_courier'] ? 1 : 0);

        return back()->with('success', 'Ayarlar kaydedildi.');
    }
}
