<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AuthorizationStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Müşterinin ödeme geçmişi. Bakiye YOK — Getiren para tutmaz; burada yalnızca
 * sipariş başına açılan provizyonlar ve akıbetleri (tahsil / çözüldü) listelenir.
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $mine = PaymentAuthorization::whereHas(
            'order',
            fn ($q) => $q->where('customer_id', $request->user()->id),
        );

        $authorizations = (clone $mine)
            ->with('order:id,code,raw_text')
            ->latest('id')
            ->paginate(15)
            ->through(fn (PaymentAuthorization $a) => [
                'id' => $a->id,
                'order_id' => $a->order_id,
                'order_code' => $a->order?->code,
                'order_text' => $a->order?->raw_text,
                'status_label' => $a->status->label(),
                'tone' => $a->status->tone(),
                'amount' => (float) $a->amount,
                'captured_amount' => $a->captured_amount !== null ? (float) $a->captured_amount : null,
                'released_amount' => $a->releasedAmount(),
                'note' => $a->note,
                'provider_ref' => $a->provider_ref,
                'at' => $a->created_at?->format('d.m.Y H:i'),
            ]);

        return Inertia::render('Customer/Payments', [
            'authorizations' => $authorizations,
            'summary' => [
                // Şu an kartında ayrılmış duran tutar (henüz kesilmedi)
                'open' => (float) (clone $mine)->where('status', AuthorizationStatus::Authorized)->sum('amount'),
                'captured' => (float) (clone $mine)->where('status', AuthorizationStatus::Captured)->sum('captured_amount'),
                // Kesilmeyip müşteriye bırakılan kısım: iptal edilen provizyonun tamamı + fişten artan
                'released' => (float) (clone $mine)
                    ->whereIn('status', [AuthorizationStatus::Captured, AuthorizationStatus::Voided])
                    ->sum(DB::raw('amount - COALESCE(captured_amount, 0)')),
            ],
        ]);
    }
}
