<?php

namespace App\Http\Controllers\Concerns;

use App\Rules\Iban;
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
        $iban = Iban::normalize($data['iban'] ?? null);

        // Biçim + mod-97 checksum. Sadece biçime bakmak yetmez: tek hanesi yanlış
        // yazılmış IBAN da "TR + 24 rakam" kalıbına uyar ve para yanlış hesaba gider.
        if ($iban !== null && ! Iban::isValid($iban)) {
            throw ValidationException::withMessages([
                'iban' => 'Geçerli bir TR IBAN girin (TR + 24 rakam, kontrol hanesi tutmalı).',
            ]);
        }

        $request->user()->update([
            'iban' => $iban,
            'iban_holder' => $iban ? ($data['iban_holder'] ?? null) : null,
        ]);
    }
}
