<?php

namespace App\Support;

use App\Models\Setting;

/**
 * İşletme/şirket kimlik bilgisinin tek erişim noktası.
 *
 * Öncelik: veritabanı (admin panelinden düzenlenen) → yoksa config/company.php (env).
 * Böylece env başlangıç/yedek değer verir; admin panelden değiştirince DB'den okunur
 * (config cache'lense bile canlı çalışır).
 */
class Company
{
    /** Panelden düzenlenebilen alanlar (config/company.php anahtarlarıyla aynı). */
    public const FIELDS = [
        'name', 'legal_name', 'owner', 'type',
        'tax_office', 'tax_no', 'mersis', 'etbis', 'nace',
        'address', 'phone', 'email', 'kep', 'website', 'hours', 'service_areas',
    ];

    public static function get(string $key): string
    {
        // DB'de kayıt VARSA (boş string dahil) ona uyulur; böylece admin bir alanı
        // boşaltarak gizleyebilir. Yalnızca hiç kayıt yoksa env yedeğine düşülür.
        $override = Setting::get("company_{$key}");
        $value = $override !== null ? $override : config("company.{$key}");

        return trim((string) $value);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn (string $key) => [$key => self::get($key)])->all();
    }

    /** Hukuki metinler hâlâ taslak mı (TASLAK bandı gösterilsin mi). */
    public static function draft(): bool
    {
        $override = Setting::get('company_legal_draft');

        return $override !== null
            ? (bool) (int) $override
            : (bool) config('company.legal_draft', true);
    }
}
