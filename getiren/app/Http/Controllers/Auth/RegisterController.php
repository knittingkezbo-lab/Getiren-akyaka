<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /** Kayıt (self-register): yalnızca müşteri veya kurye. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in([UserRole::Customer->value, UserRole::Courier->value])],
            'password' => ['required', 'confirmed', 'min:8'],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Devam etmek için koşulları onaylamalısın.',
        ]);

        $role = UserRole::from($data['role']);
        $requireVerification = (bool) config('features.email_verification');

        $user = User::create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $role,
            'password' => $data['password'], // 'hashed' cast otomatik hash'ler
        ]);

        // email_verified_at Fillable değil (güvenlik) → doğrulama kapalıysa açıkça işaretle
        if (! $requireVerification) {
            $user->markEmailAsVerified();
        }

        // Müşteriye cüzdan aç
        if ($role === UserRole::Customer) {
            $user->wallet()->create(['balance' => 0, 'reserved' => 0, 'currency' => 'TRY']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        // Doğrulama açıksa: doğrulama linkini gönder + "e-postanı doğrula" ekranına yönlendir
        if ($requireVerification) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route($user->role->homeRoute());
    }
}
