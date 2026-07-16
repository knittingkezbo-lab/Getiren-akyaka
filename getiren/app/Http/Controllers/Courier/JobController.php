<?php

namespace App\Http\Controllers\Courier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PriceHint;
use App\Notifications\OrderNotification;
use App\Payments\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        // Yarış: iki kurye aynı anda "üstlen" derse ikisi de route binding'den gelen ESKİ
        // anlık görüntüyü kontrol edip ikisi de geçiyordu; ikincisi birincinin üstüne
        // yazıyordu. Koşulu UPDATE'in kendisine taşıyoruz — kazananı veritabanı seçer.
        $claimed = Order::whereKey($order->id)
            ->where('status', OrderStatus::Reserved)
            ->whereNull('courier_id')
            ->update([
                'courier_id' => $request->user()->id,
                'status' => OrderStatus::Assigned,
            ]);

        if ($claimed === 0) {
            return redirect()->route('courier.dashboard')
                ->with('error', 'Bu işi başka bir kurye üstlendi.');
        }

        $order->refresh();

        $order->customer->notify(new OrderNotification($order, 'Kuryen atandı', "#{$order->code} siparişini {$request->user()->name} üstlendi.", event: 'assigned'));

        return redirect()->route('courier.jobs.show', $order)->with('success', $order->code.' üstlenildi.');
    }

    /** Durumu ilerlet: assigned → shopping → on_the_way. */
    public function advance(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->courier_id !== $request->user()->id, 403);

        $current = $order->status;

        $next = match ($current) {
            OrderStatus::Assigned => OrderStatus::Shopping,
            OrderStatus::Shopping => OrderStatus::OnTheWay,
            default => null,
        };
        abort_if($next === null, 422);

        // Geçişi durumun kendisine koşulla: çift dokunuşta ikinci istek 0 satır günceller,
        // böylece müşteriye "siparişin yolda" bildirimi iki kez gitmez.
        $moved = Order::whereKey($order->id)
            ->where('courier_id', $request->user()->id)
            ->where('status', $current)
            ->update(['status' => $next]);

        if ($moved === 0) {
            return back()->with('error', 'Sipariş bu sırada değişti — sayfayı tazele.');
        }

        $order->refresh();

        if ($next === OrderStatus::OnTheWay) {
            $order->customer->notify(new OrderNotification($order, 'Siparişin yolda', "#{$order->code} yola çıktı, birazdan kapında.", event: 'on_the_way'));
        }

        return back()->with('success', 'Durum: '.$next->label());
    }

    /**
     * Fiş gir ve kapat = SETTLE. "Fişe göre kes → fazlasını iade et"in gerçekleştiği yer.
     * Kısmi tahsilde provizyonun kalanı sağlayıcı tarafından çözülür — ayrıca iade çağrısı gerekmez.
     * Çift-settle'ı satır kilidi + kilit altında yeniden okunan durum önler (geçit katmanının
     * assertOpen'ı son savunma hattı; ona tek başına güvenilmez).
     */
    public function settle(Request $request, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        abort_if($order->courier_id !== $request->user()->id, 403);
        abort_unless(in_array($order->status, [OrderStatus::Shopping, OrderStatus::OnTheWay], true), 422);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.actual_price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        // Fiş, müşterinin kartından ne kesileceğini belirler: siparişin kalem kümesiyle
        // BİREBİR eşleşmeli. Eskiden bulunamayan satır sessizce atlanıyordu — tekrar eden
        // kalem fişi şişirip fazla tahsilata, eksik kalem de eksik fişe yol açıyordu.
        $expected = $order->items()->pluck('id')->map(intval(...))->sort()->values()->all();
        $sent = collect($data['items'])->pluck('id')->map(intval(...))->sort()->values()->all();

        if ($sent !== $expected) {
            throw ValidationException::withMessages([
                'items' => 'Fiş siparişin kalemleriyle eşleşmiyor: her kalem tam olarak bir kez gönderilmeli.',
            ]);
        }

        DB::transaction(function () use ($order, $data, $gateway, $request) {
            // Satırı kilitle ve durumu kilit ALTINDA yeniden oku. Yukarıdaki kontrol route
            // binding'den gelen eski anlık görüntüye bakıyor: çift gönderimde iki istek de
            // aynı AÇIK provizyonu görüp sağlayıcıya iki tahsilat çağrısı yapabilirdi.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->courier_id !== $request->user()->id, 403);
            abort_unless(in_array($locked->status, [OrderStatus::Shopping, OrderStatus::OnTheWay], true), 422);

            $items = $locked->items()->get()->keyBy('id');
            $receipt = 0.0;

            foreach ($data['items'] as $row) {
                $item = $items->get((int) $row['id']);
                $item->update(['actual_price' => $row['actual_price']]);
                $receipt += (float) $row['actual_price'];
            }

            $serviceFee = (float) $locked->service_fee;
            $reserved = (float) $locked->reserved_amount;
            $total = round($receipt + $serviceFee, 2);

            $auth = $locked->activeAuthorization();
            abort_if($auth === null, 422); // provizyonu olmayan sipariş kapatılamaz

            if ($total <= $reserved) {
                // Fiş kadarını kes; provizyonun kalanı sağlayıcıda serbest kalır = iade
                $gateway->capture($auth, $total);

                $locked->update([
                    'status' => OrderStatus::Delivered,
                    'actual_receipt_amount' => $receipt,
                    'captured_amount' => $total,
                    'refund_amount' => round($reserved - $total, 2),
                    'delivered_at' => now(),
                ]);
            } else {
                // Fiş provizyonu aştı: provizyona dokunulmaz, müşteriden fark beklenir
                $locked->update([
                    'status' => OrderStatus::RequiresExtraPayment,
                    'actual_receipt_amount' => $receipt,
                    'extra_required_amount' => round($total - $reserved, 2),
                ]);
            }
        });

        $order->refresh();

        // Gerçek fiyatlarla sözlüğü besle (hem yeni kalemi ekle hem bilinenin fiyatını tazele)
        $this->learnItemPrices($order);

        if ($order->status === OrderStatus::Delivered) {
            $order->customer->notify(new OrderNotification($order, 'Siparişin teslim edildi', "#{$order->code} teslim edildi. Fazla provizyon iade edildi.", event: 'delivered'));
            $msg = $order->code.' teslim edildi · '.number_format((float) $order->refund_amount, 0, ',', '.').' TL iade.';
        } else {
            $order->customer->notify(new OrderNotification($order, 'Ek ödeme gerekiyor', "#{$order->code} için fiş provizyonu aştı — {$order->extra_required_amount} TL ek ödeme gerekiyor.", event: 'extra'));
            $msg = $order->code.' · fiş provizyonu aştı, ek ödeme bekleniyor.';
        }

        return redirect()->route('courier.dashboard')->with('success', $msg);
    }

    /**
     * Sözlüğü GERÇEK fiyatlarla besle. Kuryenin girdiği tutar (fiş ya da elle giriş
     * fark etmez) bu işin tek gerçek kaynağıdır: Akyaka'daki dükkânda fiilen ödenen para.
     *
     * Bilinen kalemin fiyatı da güncellenir — sadece bilinmeyeni eklemek yetmez,
     * yoksa sözlük enflasyonun gerisinde kalır. İlk gerçek gözlem tahmini doğrudan
     * ezer (hızlı yakınsama), sonrakiler yumuşatılır (bkz. PriceHint::recordObservation).
     */
    private function learnItemPrices(Order $order): void
    {
        foreach ($order->items()->get() as $item) {
            $qty = max(1, (int) $item->qty);

            if ($item->actual_price === null || (float) $item->actual_price <= 0) {
                continue;
            }

            $keyword = $this->keywordFrom($item->name);

            if ($keyword === null) {
                continue;
            }

            $unitPrice = round((float) $item->actual_price / $qty, 2);

            $hint = PriceHint::whereRaw('LOWER(keyword) = ?', [$keyword])->first();

            if ($hint === null) {
                $hint = PriceHint::create([
                    'keyword' => $keyword,
                    'category' => 'öğrenilen',
                    'unit_price' => $unitPrice,
                    'is_active' => true,
                ]);
            }

            $hint->recordObservation($unitPrice);
        }
    }

    /** Kalem adından sözlük anahtarı üret: "2 kutu peynir" → "peynir"; uygun değilse null. */
    private function keywordFrom(string $name): ?string
    {
        $keyword = trim(preg_replace(
            '/^\d+\s*(kutu|adet|paket|kg|gr|gram|şişe|litre|lt|ml|dilim|top)?\s*/iu',
            '',
            mb_strtolower($name, 'UTF-8'),
        ));

        if (mb_strlen($keyword) < 2 || mb_strlen($keyword) > 30 || is_numeric($keyword)) {
            return null;
        }

        return $keyword;
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
