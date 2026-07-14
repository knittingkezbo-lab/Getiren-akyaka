<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Concerns\ManagesBankInfo;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ManagesBankInfo;

    /** Kuryenin profilden aç/kapat yapabildiği bildirim olayları. */
    private const COURIER_EVENTS = ['new_job', 'assigned_courier', 'cancelled'];

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Courier/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'notifications' => [
                'notify_email' => (bool) $user->notify_email,
                'notify_web' => (bool) $user->notify_web,
                'events' => $user->eventPrefs(self::COURIER_EVENTS),
            ],
            'bank' => [
                'iban' => $user->iban,
                'iban_holder' => $user->iban_holder,
            ],
        ]);
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
        $events = collect(self::COURIER_EVENTS)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($provided[$key] ?? true)])
            ->all();

        $request->user()->update([
            'notify_email' => $data['notify_email'],
            'notify_web' => $data['notify_web'],
            'notification_events' => $events,
        ]);

        return back()->with('success', 'Bildirim tercihlerin güncellendi.');
    }

    public function updateBank(Request $request): RedirectResponse
    {
        $this->saveBankInfo($request);

        return back()->with('success', 'Banka bilgilerin güncellendi.');
    }
}
