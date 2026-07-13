<?php

namespace App\Http\Controllers\Customer;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('wallet');
        $wallet = $user->wallet;

        $transactions = $wallet
            ? $wallet->transactions()->with('order:id,code')->latest()->take(30)->get()
                ->map(fn (WalletTransaction $t) => $this->row($t))->all()
            : [];

        return Inertia::render('Customer/Wallet', [
            'balance' => (float) ($wallet?->balance ?? 0),
            'reserved' => (float) ($wallet?->reserved ?? 0),
            'transactions' => $transactions,
            'presets' => [250, 500, 1000],
        ]);
    }

    public function topup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:50', 'max:10000'],
        ]);

        $user = $request->user()->loadMissing('wallet');
        $wallet = $user->wallet ?? $user->wallet()->create(['balance' => 0, 'reserved' => 0, 'currency' => 'TRY']);

        $wallet->recordTransaction(TransactionType::TopUp, (float) $data['amount'], 0, null, 'Demo bakiye ekleme');

        return redirect()->route('customer.wallet')->with(
            'success',
            number_format((float) $data['amount'], 0, ',', '.').' TL eklendi (demo).',
        );
    }

    /** Deftere göre kullanıcıya gösterilecek hareket satırı. */
    private function row(WalletTransaction $t): array
    {
        // Tahsil'de amount 0 olur; harcanan tutarı reserved_delta'dan gösteririz
        $figure = $t->type === TransactionType::Capture ? (float) $t->reserved_delta : (float) $t->amount;

        return [
            'id' => $t->id,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'figure' => $figure,
            'is_positive' => $figure > 0,
            'order_code' => $t->order?->code,
            'created_at' => $t->created_at?->diffForHumans(),
        ];
    }
}
