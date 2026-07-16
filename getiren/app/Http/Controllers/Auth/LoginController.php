<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Art arda başarısız deneme sınırı. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * Sınır anahtarı e-posta + IP çiftidir.
     *
     * Yalnızca IP'ye bağlansaydı Akyaka'da ortak bir bağlantıdan giren masum kullanıcılar
     * birbirini kilitlerdi; yalnızca e-postaya bağlansaydı bir saldırgan istediği hesabı
     * kilitleyerek sahibini dışarıda bırakabilirdi (hizmet reddi).
     */
    private function throttleKey(Request $request): string
    {
        return 'login|'.Str::transliterate(Str::lower($request->string('email')->toString())).'|'.$request->ip();
    }

    /** Giriş denemesi (kendi custom auth'umuz — Laravel session guard). */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Çok fazla başarısız deneme. {$seconds} saniye sonra tekrar dene.",
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'E-posta veya şifre hatalı.',
            ]);
        }

        // Başarılı giriş sayacı sıfırlar: normal kullanıcı birikmiş denemelerle kilitlenmesin
        RateLimiter::clear($key);

        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->role->homeRoute()));
    }

    /** Çıkış. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
