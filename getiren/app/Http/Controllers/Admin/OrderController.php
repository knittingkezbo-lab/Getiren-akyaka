<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with(['customer:id,name', 'zone:id,name', 'courier:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone_id', $request->integer('zone')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $term = '%'.$request->string('q').'%';
                $qq->where('code', 'like', $term)->orWhere('raw_text', 'like', $term);
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'raw_text' => $o->raw_text,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'zone_name' => $o->zone?->name,
                'customer_name' => $o->customer?->name,
                'courier_id' => $o->courier_id,
                'courier_name' => $o->courier?->name,
                'reserved_amount' => (float) $o->reserved_amount,
                'actual_receipt_amount' => $o->actual_receipt_amount !== null ? (float) $o->actual_receipt_amount : null,
                'can_assign' => $o->status === OrderStatus::Reserved && $o->courier_id === null,
            ]);

        return Inertia::render('Admin/Orders', [
            'orders' => $orders,
            'filters' => $request->only('status', 'zone', 'q'),
            'zones' => Zone::orderBy('sort_order')->get(['id', 'name']),
            'couriers' => User::where('role', UserRole::Courier)->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
        ]);
    }

    public function assign(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'courier_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $courier = User::where('role', UserRole::Courier)->findOrFail($data['courier_id']);

        $order->update([
            'courier_id' => $courier->id,
            'status' => $order->status === OrderStatus::Reserved ? OrderStatus::Assigned : $order->status,
        ]);

        $order->customer->notify(new OrderNotification($order, 'Kuryen atandı', "#{$order->code} siparişine {$courier->name} atandı.", event: 'assigned'));
        $courier->notify(new OrderNotification($order, 'Yeni iş atandı', "#{$order->code} işi sana atandı — {$order->zone?->name}.", event: 'assigned_courier'));

        return back()->with('success', $order->code.' → '.$courier->name.' atandı.');
    }
}
