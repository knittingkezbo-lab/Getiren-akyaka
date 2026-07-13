<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /** Müşterinin profilden aç/kapat yapabildiği olay anahtarları. */
    private const CUSTOMER_EVENTS = ['assigned', 'on_the_way', 'delivered', 'extra'];

    public function edit(Request $request): Response
    {
        $user = $request->user()->loadMissing('addresses');
        $address = $user->addresses->firstWhere('is_default', true) ?? $user->addresses->first();

        return Inertia::render('Customer/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'address' => $address ? [
                'label' => $address->label,
                'line' => $address->line,
                'zone_id' => $address->zone_id,
            ] : null,
            'zones' => Zone::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'notifications' => [
                'notify_email' => (bool) $user->notify_email,
                'notify_web' => (bool) $user->notify_web,
                'events' => $this->eventPrefs($user),
            ],
        ]);
    }

    /**
     * Kullanıcının olay tercihlerini bilinen anahtarlar üzerinden normalize eder (eksik = açık).
     *
     * @return array<string, bool>
     */
    private function eventPrefs(User $user): array
    {
        $prefs = $user->notification_events ?? [];

        return collect(self::CUSTOMER_EVENTS)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($prefs[$key] ?? true)])
            ->all();
    }

    public function updateInfo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Bilgilerin güncellendi.');
    }

    public function updateAddress(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'line' => ['required', 'string', 'max:255'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $request->user()->addresses()->updateOrCreate(
            ['is_default' => true],
            ['label' => $data['label'], 'line' => $data['line'], 'zone_id' => $data['zone_id'] ?? null],
        );

        return back()->with('success', 'Adresin güncellendi.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $request->user()->update(['password' => $data['password']]); // 'hashed' cast

        return back()->with('success', 'Şifren güncellendi.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_email' => ['required', 'boolean'],
            'notify_web' => ['required', 'boolean'],
            'events' => ['array'],
            'events.*' => ['boolean'],
        ]);

        // Yalnızca bilinen olay anahtarlarını sakla (eksik = açık)
        $provided = $data['events'] ?? [];
        $events = collect(self::CUSTOMER_EVENTS)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($provided[$key] ?? true)])
            ->all();

        $request->user()->update([
            'notify_email' => $data['notify_email'],
            'notify_web' => $data['notify_web'],
            'notification_events' => $events,
        ]);

        return back()->with('success', 'Bildirim tercihlerin güncellendi.');
    }
}
