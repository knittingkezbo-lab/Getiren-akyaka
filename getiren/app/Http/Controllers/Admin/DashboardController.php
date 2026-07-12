<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $days = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
        $chart = collect(range(6, 0))->map(function (int $i) use ($days) {
            $date = now()->subDays($i);

            return [
                'label' => $days[$date->dayOfWeekIso - 1],
                'count' => Order::whereDate('created_at', $date)->count(),
            ];
        })->values();

        $zones = Zone::query()
            ->withCount(['orders as today_count' => fn ($q) => $q->whereDate('created_at', today())])
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $pending = Order::query()
            ->where('status', OrderStatus::Reserved)
            ->whereNull('courier_id')
            ->with(['customer:id,name', 'zone:id,name'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'raw_text' => $o->raw_text,
                'zone_name' => $o->zone?->name,
                'customer_name' => $o->customer?->name,
                'reserved_amount' => (float) $o->reserved_amount,
                'created_at' => $o->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'kpis' => [
                'today_orders' => Order::whereDate('created_at', today())->count(),
                'revenue_today' => (float) Order::whereDate('delivered_at', today())->sum('captured_amount'),
                'blocked_total' => (float) Wallet::sum('reserved'),
                'couriers' => User::where('role', UserRole::Courier)->count(),
            ],
            'chart' => $chart,
            'zones' => $zones,
            'pending' => $pending,
            'couriers' => User::where('role', UserRole::Courier)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
