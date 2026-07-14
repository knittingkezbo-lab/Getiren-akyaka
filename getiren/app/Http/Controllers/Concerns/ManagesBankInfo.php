<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** İade/çekim için IBAN + hesap sahibi kaydı (müşteri ve kurye ortak). */
trait ManagesBankInfo
{
    protected function saveBankInfo(Request $request): void
    {
        $data = $request->validate([
            'iban' => ['nullable', 'string', 'max:40'],
            'iban_holder' => ['nullable', 'string', 'max:150'],
        ]);

        // Boşlukları ayıkla + büyük harf; boşsa null
        $iban = filled($data['iban'] ?? null)
            ? strtoupper(preg_replace('/\s+/', '', $data['iban']))
            : null;

        if ($iban !== null && ! preg_match('/^TR\d{24}$/', $iban)) {
            throw ValidationException::withMessages([
                'iban' => 'Geçerli bir TR IBAN girin (TR + 24 rakam).',
            ]);
        }

        $request->user()->update([
            'iban' => $iban,
            'iban_holder' => $iban ? ($data['iban_holder'] ?? null) : null,
        ]);
    }
}
