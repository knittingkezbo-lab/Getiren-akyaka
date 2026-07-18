<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Rules\Iban;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'iban', 'iban_holder', 'notify_email', 'notify_web', 'notification_events'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'notify_email' => 'boolean',
            'notify_web' => 'boolean',
            'notification_events' => 'array',
            'approved_at' => 'datetime',

            // Finansal veri: veritabanında düz metin durmasın (bkz. encrypt migration).
            // APP_KEY'e bağlıdır — anahtar kaybolursa bu alanlar geri alınamaz.
            'iban' => 'encrypted',
            'iban_holder' => 'encrypted',
        ];
    }

    /** Ekranda tam IBAN gösterme: TR33 •••• 1326 (son 4 hane doğrulama için kalır). */
    protected function maskedIban(): Attribute
    {
        return Attribute::get(fn () => Iban::mask($this->iban));
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function ordersAsCustomer(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function ordersAsCourier(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCourier(): bool
    {
        return $this->role === UserRole::Courier;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** Hesap onaylı mı (kurye onay akışı). */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Hesabı onayla (approved_at Fillable değil → forceFill ile). */
    public function approve(): void
    {
        $this->forceFill(['approved_at' => now()])->save();
    }

    /**
     * Verilen olay anahtarları için bildirim tercihi haritası (kayıtlı değilse = açık).
     *
     * @param  array<int, string>  $keys
     * @return array<string, bool>
     */
    public function eventPrefs(array $keys): array
    {
        $prefs = $this->notification_events ?? [];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => (bool) ($prefs[$key] ?? true)])
            ->all();
    }
}
