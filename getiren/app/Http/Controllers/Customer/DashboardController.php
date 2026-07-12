<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user()->loadMissing('wallet');
        $wallet = $user->wallet;

        $active = $user->ordersAsCustomer()
            ->whereIn('status', [
                OrderStatus::Reserved,
                OrderStatus::Assigned,
                OrderStatus::Shopping,
                OrderStatus::OnTheWay,
            ])
            ->with(['courier:id,name', 'zone:id,name'])
            ->latest()
            ->first();

        $recent = $user->ordersAsCustomer()
            ->with('zone:id,name')
            ->latest()
            ->take(4)
            ->get();

        return Inertia::render('Customer/Dashboard', [
            'stats' => [
                'balance' => (float) ($wallet?->balance ?? 0),
                'reserved' => (float) ($wallet?->reserved ?? 0),
                'month_count' => $user->ordersAsCustomer()
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count(),
            ],
            'activeOrder' => $active ? $this->orderCard($active) : null,
            'recentOrders' => $recent->map(fn (Order $o) => $this->orderCard($o))->all(),
        ]);
    }

    private function orderCard(Order $o): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code,
            'raw_text' => $o->raw_text,
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'zone_name' => $o->zone?->name,
            'courier_name' => $o->courier?->name,
            'reserved_amount' => (float) $o->reserved_amount,
            'actual_receipt_amount' => $o->actual_receipt_amount !== null ? (float) $o->actual_receipt_amount : null,
            'created_at' => $o->created_at?->diffForHumans(),
        ];
    }
}
