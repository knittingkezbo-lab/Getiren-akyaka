<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IBAN ve hesap sahibini şifreli sakla.
 *
 * İki iş bir arada yapılmalı:
 *  1) Sütunları genişlet — şifreli değer ~250 karakter, sütun varchar(34) idi;
 *     genişletmeden yazmak veriyi kırpar ya da hata verir.
 *  2) Mevcut düz metin satırları şifrele — yoksa model onları çözmeye çalışıp patlar.
 *
 * Ham sorgu kullanılır (DB::table): Eloquent cast'i devrede olsaydı okuma sırasında
 * düz metni çözmeye çalışırdı.
 *
 * NOT: Şifreleme APP_KEY'e bağlıdır. APP_KEY kaybedilirse IBAN'lar geri alınamaz —
 * anahtarı yedekle. (Zaten oturum/çerezler de aynı anahtara bağlı.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('iban')->nullable()->change();
            $table->text('iban_holder')->nullable()->change();
        });

        DB::table('users')
            ->where(function ($q) {
                $q->whereNotNull('iban')->orWhereNotNull('iban_holder');
            })
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'iban' => $this->encryptOnce($user->iban),
                    'iban_holder' => $this->encryptOnce($user->iban_holder),
                ]);
            });
    }

    /**
     * Zaten şifreli bir değeri ikinci kez şifrelemez.
     * Migration yarıda kalıp tekrar koşarsa veriyi bozmasın diye.
     */
    private function encryptOnce(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            Crypt::decryptString($value);

            return $value; // çözülebiliyorsa zaten şifreli
        } catch (Throwable) {
            return Crypt::encryptString($value);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where(function ($q) {
                $q->whereNotNull('iban')->orWhereNotNull('iban_holder');
            })
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'iban' => $this->decryptOnce($user->iban),
                    'iban_holder' => $this->decryptOnce($user->iban_holder),
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('iban', 34)->nullable()->change();
            $table->string('iban_holder', 150)->nullable()->change();
        });
    }

    private function decryptOnce(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value; // zaten düz metin
        }
    }
};
