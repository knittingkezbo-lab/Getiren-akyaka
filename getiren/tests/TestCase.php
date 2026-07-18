<?php

namespace Tests;

use App\Enums\AuthorizationStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * PHP testleri derlenmiş frontend varlıklarına bağımlı olmamalı.
     *
     * Tam sayfa render eden testler Blade'deki @vite direktifine düşüyor ve o da
     * public/build/manifest.json arıyordu. Dosya geliştirme makinesinde vardı (sürekli
     * vite build koşuyoruz) ama temiz bir checkout'ta yok — CI'da 5 test bu yüzden
     * "Vite manifest not found" ile düşüyordu. Yani testler görünmez biçimde
     * "önce npm run build koşulmuş olmasına" bağlıydı.
     *
     * withoutVite() bu bağı keser: sunucu davranışını sınayan testler, varlıkların
     * derlenip derlenmediğinden bağımsız olarak aynı sonucu verir.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function makeCustomer(): User
    {
        return User::factory()->create(['role' => UserRole::Customer]);
    }

    protected function makeCourier(): User
    {
        return User::factory()->create(['role' => UserRole::Courier]);
    }

    /**
     * Provizyon değişmezi — kaldırılan cüzdan defterinin yerini alan kural:
     *  1) Bir siparişin aynı anda en fazla BİR açık provizyonu olabilir.
     *  2) Kapanmış her provizyonda: kesilen + geri bırakılan = provizyona alınan.
     * Para hareketinin olduğu her akışta bu korunmalı.
     */
    protected function assertAuthorizationsConsistent(Order $order): void
    {
        $authorizations = $order->authorizations()->get();

        $this->assertLessThanOrEqual(
            1,
            $authorizations->where('status', AuthorizationStatus::Authorized)->count(),
            'Siparişin birden fazla açık provizyonu var',
        );

        $closed = $authorizations->whereIn('status', [AuthorizationStatus::Captured, AuthorizationStatus::Voided]);

        foreach ($closed as $authorization) {
            $this->assertEqualsWithDelta(
                (float) $authorization->amount,
                (float) $authorization->captured_amount + $authorization->releasedAmount(),
                0.001,
                "Provizyon #{$authorization->id}: kesilen + geri bırakılan, provizyona alınanı vermiyor",
            );
        }
    }
}
