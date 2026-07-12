<?php

namespace Tests;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeCustomer(float $balance = 0): User
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);
        $wallet = $user->wallet()->create(['balance' => 0, 'reserved' => 0, 'currency' => 'TRY']);

        // Açılış bakiyesi deftere yazılır (seeder gibi) — ledger tutarlılığı korunsun
        if ($balance > 0) {
            $wallet->recordTransaction(TransactionType::TopUp, $balance, 0, null, 'Açılış bakiyesi');
        }

        return $user->refresh();
    }

    protected function makeCourier(): User
    {
        return User::factory()->create(['role' => UserRole::Courier]);
    }

    /**
     * Cüzdan defteri değişmezi: bakiye == Σamount, bloke == Σreserved_delta.
     * Tüm para akışlarında bu korunmalı (ledger'ın var oluş sebebi).
     */
    protected function assertLedgerConsistent(Wallet $wallet): void
    {
        $wallet->refresh();
        $sumAmount = (float) $wallet->transactions()->sum('amount');
        $sumReserved = (float) $wallet->transactions()->sum('reserved_delta');

        $this->assertEqualsWithDelta((float) $wallet->balance, $sumAmount, 0.001, 'Bakiye defter toplamıyla (Σamount) uyuşmuyor');
        $this->assertEqualsWithDelta((float) $wallet->reserved, $sumReserved, 0.001, 'Bloke defter toplamıyla (Σreserved_delta) uyuşmuyor');
    }
}
