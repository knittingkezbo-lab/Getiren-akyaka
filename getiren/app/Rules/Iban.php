<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * TR IBAN doğrulaması — biçim + mod-97 checksum (ISO 13616 / ISO 7064).
 *
 * Biçim kontrolü tek başına yetmez: "TR" + 24 rakam kalıbına uyan ama tek hanesi
 * yanlış yazılmış bir IBAN da geçerdi. Checksum, insanın yaptığı hane hatalarını
 * yakalamak için vardır — para yanlış veya var olmayan bir hesaba gitmesin diye.
 */
class Iban implements ValidationRule
{
    /** TR IBAN'ı sabit 26 karakterdir: TR + 2 kontrol hanesi + 22 hane. */
    public const LENGTH = 26;

    /** Boşluk/küçük harf ayıklayıp standart biçime getirir. */
    public static function normalize(?string $iban): ?string
    {
        if (! filled($iban)) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    public static function isValid(?string $iban): bool
    {
        $iban = self::normalize($iban);

        if ($iban === null || ! preg_match('/^TR\d{24}$/', $iban)) {
            return false;
        }

        return self::mod97($iban) === 1;
    }

    /**
     * ISO 7064 MOD-97-10: ilk 4 karakter sona alınır, harfler sayıya çevrilir
     * (A=10 … Z=35) ve kalan 1 olmalıdır. Sayı 26 hane olduğu için parça parça
     * mod alınır — tamsayı taşmasını önler.
     */
    private static function mod97(string $iban): int
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder;
    }

    /** Ekranda gösterim için: TR33 •••• 1326 — son 4 hane doğrulanabilsin diye kalır. */
    public static function mask(?string $iban): ?string
    {
        $iban = self::normalize($iban);

        if ($iban === null || strlen($iban) < 8) {
            return $iban;
        }

        return substr($iban, 0, 4).' •••• '.substr($iban, -4);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(is_string($value) ? $value : null)) {
            $fail('Geçerli bir TR IBAN girin (TR + 24 rakam, kontrol hanesi tutmalı).');
        }
    }
}
