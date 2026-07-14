<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(Request $request): Response
    {
        $active = [OrderStatus::Assigned, OrderStatus::Shopping, OrderStatus::OnTheWay];

        $couriers = User::query()
            ->where('role', UserRole::Courier)
            ->whereNotNull('approved_at')
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

        // Onay bekleyen kurye başvuruları
        $pending = User::query()
            ->where('role', UserRole::Courier)
            ->whereNull('approved_at')
            ->latest()
            ->get(['id', 'name', 'email', 'phone', 'created_at'])
            ->map(fn (User $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'applied_at' => $c->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/Couriers', [
            'couriers' => $couriers,
            'pending' => $pending,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::Courier, 404);

        DB::transaction(function () use ($user) {
            $user->approve();

            AuditLog::record(
                AuditAction::CourierApproved,
                "{$user->name} kurye olarak onaylandı — artık iş alabilir.",
                $user,
                $user->name,
                ['e-posta' => $user->email],
            );
        });

        return back()->with('success', $user->name.' onaylandı — artık iş alabilir.');
    }

    public function reject(User $user): RedirectResponse
    {
        abort_unless($user->role === UserRole::Courier && ! $user->isApproved(), 422);

        $name = $user->name;

        DB::transaction(function () use ($user, $name) {
            // Kullanıcı satırı siliniyor; kaydın okunabilirliği kopyalanan ad ve e-postadan geliyor
            AuditLog::record(
                AuditAction::CourierRejected,
                "{$name} kurye başvurusu reddedildi ve hesabı silindi.",
                $user,
                $name,
                ['e-posta' => $user->email],
            );

            $user->delete();
        });

        return back()->with('success', $name.' başvurusu reddedildi.');
    }
}
