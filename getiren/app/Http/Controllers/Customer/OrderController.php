<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderNotification;
use App\Services\OrderEstimator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('filter')->toString();
        $statuses = match ($filter) {
            'active' => [OrderStatus::Reserved, OrderStatus::Assigned, OrderStatus::Shopping, OrderStatus::OnTheWay],
            'delivered' => [OrderStatus::Delivered],
            'extra' => [OrderStatus::RequiresExtraPayment],
            'cancelled' => [OrderStatus::Cancelled],
            default => null,
        };

        $orders = $request->user()->ordersAsCustomer()
            ->with('zone:id,name')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'raw_text' => $o->raw_text,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'zone_name' => $o->zone?->name,
                'reserved_amount' => (float) $o->reserved_amount,
                'actual_receipt_amount' => $o->actual_receipt_amount !== null ? (float) $o->actual_receipt_amount : null,
                'created_at' => $o->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Customer/Orders', [
            'orders' => $orders,
            'filter' => $filter ?: 'all',
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user()->loadMissing('wallet');

        return Inertia::render('Customer/OrderNew', [
            'zones' => Zone::where('is_active', true)->orderBy('sort_order')->get(['id', 'key', 'name', 'service_fee']),
            'priceHints' => PriceHint::where('is_active', true)->get(['keyword', 'unit_price']),
            'bufferPct' => (float) Setting::get('safety_buffer_pct', 15),
            'minOrderTotal' => (float) Setting::get('min_order_total', 0),
            'balance' => (float) ($user->wallet?->balance ?? 0),
            'addresses' => $user->addresses()->get(['id', 'label', 'line']),
        ]);
    }

    public function store(Request $request, OrderEstimator $estimator): RedirectResponse
    {
        $data = $request->validate([
            'raw_text' => ['required', 'string', 'max:1000'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'address_label' => ['nullable', 'string', 'max:100'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => 'Devam etmek için ön bilgilendirme ve kullanım şartlarını onaylamalısın.',
        ]);

        $zone = Zone::where('is_active', true)->findOrFail($data['zone_id']);
        $user = $request->user()->loadMissing('wallet');
        $wallet = $user->wallet;

        // Sunucu tarafı OTORİTER tahmin (istemciye güvenilmez)
        $est = $estimator->estimate($data['raw_text'], $zone);

        if (! $wallet || (float) $wallet->balance < $est['reserved_amount']) {
            throw ValidationException::withMessages([
                'raw_text' => 'Yetersiz bakiye. Bu sipariş için '.number_format($est['reserved_amount'], 0, ',', '.').' TL gerekiyor.',
            ]);
        }

        $order = DB::transaction(function () use ($user, $wallet, $zone, $data, $est) {
            $order = $user->ordersAsCustomer()->create([
                'code' => $this->nextCode(),
                'zone_id' => $zone->id,
                'raw_text' => $data['raw_text'],
                'address_label' => $data['address_label'] ?? null,
                'address_text' => $data['address_text'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'items_total' => $est['items_total'],
                'safety_buffer' => $est['safety_buffer'],
                'service_fee' => $est['service_fee'],
                'reserved_amount' => $est['reserved_amount'],
                'status' => OrderStatus::Reserved,
                'terms_version' => config('features.terms_version'),
                'reserved_at' => now(),
            ]);

            $order->items()->createMany($est['items']);

            // Bloke: kullanılabilir bakiyeden düş, reserved'a ekle (tek ledger satırı)
            $wallet->recordTransaction(
                TransactionType::Hold,
                -$est['reserved_amount'],
                $est['reserved_amount'],
                $order,
                'Sipariş provizyona alındı',
            );

            return $order;
        });

        // Kuryelere yeni iş fırsatı (yalnızca web zil — her siparişte e-posta spam olmasın)
        $couriers = User::where('role', UserRole::Courier)->get();
        if ($couriers->isNotEmpty()) {
            Notification::send($couriers, new OrderNotification(
                $order,
                'Yeni iş fırsatı',
                $zone->name.' · '.Str::limit($data['raw_text'], 30).' · '.number_format($est['reserved_amount'], 0, ',', '.').' TL',
                ['database'],
                '/kurye',
                'new_job',
            ));
        }

        return redirect()->route('customer.orders.show', $order)->with(
            'success',
            $order->code.' oluşturuldu · '.number_format($est['reserved_amount'], 0, ',', '.').' TL provizyona alındı.',
        );
    }

    public function show(Request $request, Order $order): Response
    {
        abort_if($order->customer_id !== $request->user()->id, 403);

        $order->load(['items', 'courier:id,name', 'zone:id,name']);

        return Inertia::render('Customer/OrderTrack', [
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'raw_text' => $order->raw_text,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'zone_name' => $order->zone?->name,
                'address_text' => $order->address_text,
                'customer_note' => $order->customer_note,
                'courier_name' => $order->courier?->name,
                'items_total' => (float) $order->items_total,
                'safety_buffer' => (float) $order->safety_buffer,
                'service_fee' => (float) $order->service_fee,
                'reserved_amount' => (float) $order->reserved_amount,
                'actual_receipt_amount' => $order->actual_receipt_amount !== null ? (float) $order->actual_receipt_amount : null,
                'captured_amount' => $order->captured_amount !== null ? (float) $order->captured_amount : null,
                'refund_amount' => $order->refund_amount !== null ? (float) $order->refund_amount : null,
                'extra_required_amount' => $order->extra_required_amount !== null ? (float) $order->extra_required_amount : null,
                'created_at' => $order->created_at?->diffForHumans(),
                'can_cancel' => in_array($order->status, [OrderStatus::Reserved, OrderStatus::Assigned], true),
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->name,
                    'qty' => $i->qty,
                    'estimated_price' => (float) $i->estimated_price,
                    'actual_price' => $i->actual_price !== null ? (float) $i->actual_price : null,
                ])->all(),
            ],
            'balance' => (float) ($request->user()->wallet?->balance ?? 0),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->customer_id !== $request->user()->id, 403);
        abort_unless(in_array($order->status, [OrderStatus::Reserved, OrderStatus::Assigned], true), 422);

        $user = $request->user()->loadMissing('wallet');
        $courier = $order->courier; // iptal öncesi atanmış kurye (varsa haber verilecek)

        DB::transaction(function () use ($user, $order) {
            // Yarış koşuluna karşı satırı kilitle ve durumu yeniden doğrula:
            // eşzamanlı çift iptal → çift iade (ledger bozulması) önlenir.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [OrderStatus::Reserved, OrderStatus::Assigned], true), 422);

            // Bloke çöz: reserved'daki tutar tekrar kullanılabilir bakiyeye döner (tek ledger satırı)
            $user->wallet->recordTransaction(
                TransactionType::Release,
                (float) $locked->reserved_amount,
                -(float) $locked->reserved_amount,
                $locked,
                'Sipariş iptal · provizyon çözüldü',
            );
            $locked->update(['status' => OrderStatus::Cancelled]);
        });

        // Sipariş atanmış bir kuryedeyse iptalden haberdar et
        if ($courier) {
            $courier->notify(new OrderNotification(
                $order,
                'Sipariş iptal edildi',
                "#{$order->code} müşteri tarafından iptal edildi.",
                event: 'cancelled',
            ));
        }

        return redirect()->route('customer.dashboard')->with(
            'success',
            $order->code.' iptal edildi · '.number_format((float) $order->reserved_amount, 0, ',', '.').' TL provizyon çözüldü.',
        );
    }

    /**
     * Ek ödeme tamamlama: fiş blokeyi aşan sipariş için müşteri farkı öder.
     * Kalan bloke tahsil edilir + fark kullanılabilir bakiyeden düşülür (ExtraCharge).
     */
    public function payExtra(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->customer_id !== $request->user()->id, 403);
        abort_unless($order->status === OrderStatus::RequiresExtraPayment, 422);

        $user = $request->user()->loadMissing('wallet');
        $wallet = $user->wallet;
        $extra = (float) $order->extra_required_amount;
        $reserved = (float) $order->reserved_amount;
        $captured = $reserved + $extra;

        if (! $wallet || (float) $wallet->balance < $extra) {
            throw ValidationException::withMessages([
                'extra' => 'Yetersiz bakiye. Ek ödeme için '.number_format($extra, 0, ',', '.').' TL gerekiyor.',
            ]);
        }

        DB::transaction(function () use ($wallet, $order, $extra, $reserved, $captured) {
            // Kullanılabilirden ek tutar düşülür, bloke edilen tamamen tahsil edilir
            $wallet->recordTransaction(
                TransactionType::ExtraCharge,
                -$extra,
                -$reserved,
                $order,
                'Ek ödeme ve tahsil',
                ['captured' => $captured, 'extra' => $extra],
            );

            $order->update([
                'status' => OrderStatus::Delivered,
                'captured_amount' => $captured,
                'refund_amount' => 0,
                'delivered_at' => now(),
            ]);
        });

        return redirect()->route('customer.orders.show', $order)->with(
            'success',
            $order->code.' · '.number_format($extra, 0, ',', '.').' TL ek ödeme alındı, sipariş tamamlandı.',
        );
    }

    /** Demo sipariş kodu (A24-119, A24-120, ...). */
    private function nextCode(): string
    {
        return 'A24-'.(((int) Order::max('id')) + 114);
    }
}
