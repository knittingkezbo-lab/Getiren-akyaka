<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(Request $request): Response
    {
        $active = [OrderStatus::Assigned, OrderStatus::Shopping, OrderStatus::OnTheWay];

        $couriers = User::query()
            ->where('role', UserRole::Courier)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(function (User $c) use ($active) {
                $activeCount = $c->ordersAsCourier()->whereIn('status', $active)->count();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'active' => $activeCount,
                    'delivered_today' => $c->ordersAsCourier()
                        ->where('status', OrderStatus::Delivered)
                        ->whereDate('delivered_at', today())
                        ->count(),
                    'earnings_today' => (float) $c->ordersAsCourier()
                        ->where('status', OrderStatus::Delivered)
                        ->whereDate('delivered_at', today())
                        ->sum('service_fee'),
                    'status' => $activeCount > 0 ? 'busy' : 'available',
                ];
            });

        return Inertia::render('Admin/Couriers', [
            'couriers' => $couriers,
        ]);
    }
}
