<?php

namespace App\Http\Controllers\Courier;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PriceHint;
use App\Notifications\OrderNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    private const ACTIVE = [OrderStatus::Assigned, OrderStatus::Shopping, OrderStatus::OnTheWay];

    public function index(Request $request): Response
    {
        $courier = $request->user();

        $available = Order::query()
            ->where('status', OrderStatus::Reserved)
            ->whereNull('courier_id')
            ->with(['zone:id,name', 'customer:id,name'])
            ->latest()
            ->take(10)
            ->get();

        $mine = $courier->ordersAsCourier()
            ->whereIn('status', self::ACTIVE)
            ->with(['zone:id,name', 'customer:id,name'])
            ->latest()
            ->get();

        return Inertia::render('Courier/Dashboard', [
            'available' => $available->map(fn (Order $o) => $this->card($o))->all(),
            'mine' => $mine->map(fn (Order $o) => $this->card($o))->all(),
            'stats' => [
                'delivered_today' => $courier->ordersAsCourier()->where('status', OrderStatus::Delivered)->whereDate('delivered_at', today())->count(),
                'active' => $mine->count(),
                'earnings_today' => (float) $courier->ordersAsCourier()->where('status', OrderStatus::Delivered)->whereDate('delivered_at', today())->sum('service_fee'),
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        abort_if($order->courier_id !== $request->user()->id, 403);
        $order->load(['items', 'zone:id,name', 'customer:id,name,phone']);

        return Inertia::render('Courier/Order', [
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'zone_name' => $order->zone?->name,
                'address_text' => $order->address_text,
                'customer_note' => $order->customer_note,
                'customer_name' => $order->customer?->name,
                'customer_phone' => $order->customer?->phone,
                'service_fee' => (float) $order->service_fee,
                'reserved_amount' => (float) $order->reserved_amount,
                'can_advance' => in_array($order->status, [OrderStatus::Assigned, OrderStatus::Shopping], true),
                'can_settle' => in_array($order->status, [OrderStatus::Shopping, OrderStatus::OnTheWay], true),
                'next_label' => $order->status === OrderStatus::Assigned ? 'Alışverişe başla' : ($order->status === OrderStatus::Shopping ? 'Yola çıktım' : null),
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'qty' => $i->qty,
                    'estimated_price' => (float) $i->estimated_price,
                    'actual_price' => $i->actual_price !== null ? (float) $i->actual_price : (float) $i->estimated_price,
                ])->all(),
            ],
        ]);
    }

    /** İşi üstlen: reserved + atanmamış → assigned (kurye ben). */
    public function accept(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Reserved && $order->courier_id === null, 422);

        $order->update([
            'courier_id' => $request->user()->id,
            'status' => OrderStatus::Assigned,
        ]);

        $order->customer->notify(new OrderNotification($order, 'Kuryen atandı', "#{$order->code} siparişini {$request->user()->name} üstlendi.", event: 'assigned'));

        return redirect()->route('courier.jobs.show', $order)->with('success', $order->code.' üstlenildi.');
    }

    /** Durumu ilerlet: assigned → shopping → on_the_way. */
    public function advance(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->courier_id !== $request->user()->id, 403);

        $next = match ($order->status) {
            OrderStatus::Assigned => OrderStatus::Shopping,
            OrderStatus::Shopping => OrderStatus::OnTheWay,
            default => null,
        };
        abort_if($next === null, 422);

        $order->update(['status' => $next]);

        if ($next === OrderStatus::OnTheWay) {
            $order->customer->notify(new OrderNotification($order, 'Siparişin yolda', "#{$order->code} yola çıktı, birazdan kapında.", event: 'on_the_way'));
        }

        return back()->with('success', 'Durum: '.$next->label());
    }

    /**
     * Fiş gir ve kapat = SETTLE. Ledger'ın "fişe göre kes → farkı iade" yarısı.
     * Guard (status shopping/on_the_way) çift-settle'ı önler.
     */
    public function settle(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->courier_id !== $request->user()->id, 403);
        abort_unless(in_array($order->status, [OrderStatus::Shopping, OrderStatus::OnTheWay], true), 422);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.actual_price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        $order->loadMissing('customer.wallet');
        $wallet = $order->customer->wallet;

        DB::transaction(function () use ($order, $wallet, $data) {
            $receipt = 0.0;
            foreach ($data['items'] as $row) {
                $item = $order->items()->whereKey($row['id'])->first();
                if ($item) {
                    $item->update(['actual_price' => $row['actual_price']]);
                    $receipt += (float) $row['actual_price'];
                }
            }

            $serviceFee = (float) $order->service_fee;
            $reserved = (float) $order->reserved_amount;
            $captured = $receipt + $serviceFee;

            if ($captured <= $reserved) {
                $refund = round($reserved - $captured, 2);
                // Tahsil: blokeden harcanan kısım düşülür (available değişmez)
                $wallet->recordTransaction(TransactionType::Capture, 0, -$captured, $order, 'Fişe göre tahsil', [
                    'receipt' => $receipt, 'service_fee' => $serviceFee,
                ]);
                // İade: kalan bloke tekrar kullanılabilir bakiyeye döner
                $wallet->recordTransaction(TransactionType::Refund, $refund, -$refund, $order, 'Fazla blokaj iadesi');

                $order->update([
                    'status' => OrderStatus::Delivered,
                    'actual_receipt_amount' => $receipt,
                    'captured_amount' => $captured,
                    'refund_amount' => $refund,
                    'delivered_at' => now(),
                ]);
            } else {
                // Fiş blokeyi aştı: para hareket etmez, ek ödeme beklenir
                $order->update([
                    'status' => OrderStatus::RequiresExtraPayment,
                    'actual_receipt_amount' => $receipt,
                    'extra_required_amount' => round($captured - $reserved, 2),
                ]);
            }
        });

        $order->refresh();

        // Gerçek fişten öğren: sözlükte olmayan kalemleri fiyatıyla price_hints'e ekle
        $this->learnUnknownItems($order);

        if ($order->status === OrderStatus::Delivered) {
            $order->customer->notify(new OrderNotification($order, 'Siparişin teslim edildi', "#{$order->code} teslim edildi. Fazla blokaj cüzdanına iade edildi.", event: 'delivered'));
            $msg = $order->code.' teslim edildi · '.number_format((float) $order->refund_amount, 0, ',', '.').' TL iade.';
        } else {
            $order->customer->notify(new OrderNotification($order, 'Ek ödeme gerekiyor', "#{$order->code} için fiş blokeyi aştı — {$order->extra_required_amount} TL ek ödeme gerekiyor.", event: 'extra'));
            $msg = $order->code.' · fiş blokeyi aştı, ek ödeme bekleniyor.';
        }

        return redirect()->route('courier.dashboard')->with('success', $msg);
    }

    /**
     * Gerçek fişten sözlük öğrenme: bilinmeyen kalemi (miktar eki temizlenmiş) birim
     * fiyatıyla price_hints'e ekler; sonraki siparişlerde öneri/tahmin gelişir.
     */
    private function learnUnknownItems(Order $order): void
    {
        $known = PriceHint::pluck('keyword')->map(fn ($k) => mb_strtolower($k, 'UTF-8'))->all();

        foreach ($order->items()->get() as $item) {
            if ($item->actual_price === null || (int) $item->qty < 1) {
                continue;
            }

            // Baştaki miktar/birim ekini at: "2 kutu peynir" → "peynir"
            $keyword = trim(preg_replace(
                '/^\d+\s*(kutu|adet|paket|kg|gr|gram|şişe|litre|lt|ml|dilim|top)?\s*/iu',
                '',
                mb_strtolower($item->name, 'UTF-8'),
            ));

            if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 30 || is_numeric($keyword) || in_array($keyword, $known, true)) {
                continue;
            }

            PriceHint::create([
                'keyword' => $keyword,
                'category' => 'öğrenilen',
                'unit_price' => round((float) $item->actual_price / max(1, (int) $item->qty), 2),
                'is_active' => true,
            ]);
            $known[] = $keyword;
        }
    }

    private function card(Order $o): array
    {
        return [
            'id' => $o->id,
            'code' => $o->code,
            'raw_text' => $o->raw_text,
            'status' => $o->status->value,
            'status_label' => $o->status->label(),
            'zone_name' => $o->zone?->name,
            'customer_name' => $o->customer?->name,
            'reserved_amount' => (float) $o->reserved_amount,
            'created_at' => $o->created_at?->diffForHumans(),
        ];
    }
}
