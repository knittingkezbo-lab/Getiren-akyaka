<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_topup_adds_balance_and_writes_ledger(): void
    {
        $customer = $this->makeCustomer(0);

        $this->actingAs($customer)
            ->post('/musteri/cuzdan/yukle', ['amount' => 500])
            ->assertRedirect();

        $wallet = $customer->wallet->refresh();
        $this->assertEquals(500.0, (float) $wallet->balance);
        $this->assertEquals(0.0, (float) $wallet->reserved);
        $this->assertLedgerConsistent($wallet);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'topup',
            'amount' => 500,
        ]);
    }

    public function test_topup_rejects_below_minimum(): void
    {
        $customer = $this->makeCustomer(0);

        $this->actingAs($customer)
            ->post('/musteri/cuzdan/yukle', ['amount' => 10])
            ->assertSessionHasErrors('amount');

        $this->assertEquals(0.0, (float) $customer->wallet->refresh()->balance);
    }
}
