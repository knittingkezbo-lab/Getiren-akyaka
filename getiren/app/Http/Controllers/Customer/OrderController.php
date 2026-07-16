<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PriceHint;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\OrderNotification;
use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use App\Services\OrderEstimator;
use Illuminate\Http\JsonResponse;
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
        return Inertia::render('Customer/OrderNew', [
            'acceptingOrders' => $this->intakeIsOpen(),
            'zones' => Zone::where('is_active', true)->orderBy('sort_order')->get(['id', 'key', 'name', 'service_fee']),
            // Yalnızca kelimeler: otomatik tamamlamanın fiyata ihtiyacı yok, tahmini
            // sunucu üretiyor. Fiyat sözlüğü istemciye açılmaz.
            'priceHints' => PriceHint::where('is_active', true)->orderBy('keyword')->pluck('keyword'),
            'addresses' => $request->user()->addresses()->get(['id', 'label', 'line']),
        ]);
    }

    /**
     * Canlı tahmin — ekranda gösterilen tutarın TEK otoritesi.
     *
     * Önizleme istemcide hesaplanmamalı: müşteri gördüğü tutara rıza gösteriyor, bu yüzden
     * o tutar store()'un provizyona alacağı tutarla aynı algoritmadan gelmeli. İki ayrı
     * hesap (Vue 40TL/%15 vs PHP 60TL/%35) rıza uyuşmazlığı demekti.
     */
    public function estimate(Request $request, OrderEstimator $estimator): JsonResponse
    {
        $this->abortIfIntakeClosed();

        $data = $request->validate([
            'raw_text' => ['required', 'string', 'max:1000'],
            'zone_id' => ['required', 'integer'],
        ]);

        $zone = Zone::where('is_active', true)->find($data['zone_id']);

        if ($zone === null) {
            throw ValidationException::withMessages(['zone_id' => 'Bu bölgeye şu an hizmet verilmiyor.']);
        }

        return response()->json($estimator->estimate($data['raw_text'], $zone));
    }

    /**
     * Yönetici "Siparişleri kabul et" anahtarını kapattığında dükkân gerçekten kapanmalı.
     * Tek kaynak: hem önizleme hem sipariş bu kapıdan geçer.
     */
    private function intakeIsOpen(): bool
    {
        return (bool) Setting::get('accepting_orders', 1);
    }

    private function abortIfIntakeClosed(): void
    {
        if (! $this->intakeIsOpen()) {
            throw ValidationException::withMessages([
                'raw_text' => 'Şu anda yeni sipariş alamıyoruz. Lütfen daha sonra tekrar dene.',
            ]);
        }
    }

    public function store(Request $request, OrderEstimator $estimator, PaymentGateway $gateway): RedirectResponse
    {
        $this->abortIfIntakeClosed();

        $data = $request->validate([
            'raw_text' => ['required', 'string', 'max:1000'],
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'address_label' => ['nullable', 'string', 'max:100'],
            // Adressiz sipariş kuryeyi yolsuz bırakır; provizyon çoktan alınmış olur
            'address_text' => ['required', 'string', 'min:5', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:255'],
            'terms_accepted' => ['accepted'],
        ], [
            'address_text.required' => 'Teslimat adresi gerekli — kurye nereye geleceğini bilmeli.',
            'address_text.min' => 'Adresi biraz daha açık yazar mısın?',
            'terms_accepted.accepted' => 'Devam etmek için ön bilgilendirme ve kullanım şartlarını onaylamalısın.',
        ]);

        $zone = Zone::where('is_active', true)->findOrFail($data['zone_id']);
        $user = $request->user();

        // Sunucu tarafı OTORİTER tahmin (istemciye güvenilmez)
        $est = $estimator->estimate($data['raw_text'], $zone);

        try {
            $order = DB::transaction(function () use ($user, $zone, $data, $est, $gateway) {
                $order = $user->ordersAsCustomer()->create([
                    // Gerçek kod id belli olunca yazılır (aşağıda). Buradaki geçici değer
                    // yalnızca unique kolonu doldurur; dışarı sızmaz (aynı transaction).
                    'code' => 'GEC-'.Str::uuid(),
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

                $order->update(['code' => $this->codeFor($order)]);

                $order->items()->createMany($est['items']);

                // Tahmin edilen tutar müşterinin ödeme aracında provizyona alınır (para hesaba geçmez)
                $gateway->authorize($order, (float) $est['reserved_amount'], 'Sipariş provizyonu');

                return $order;
            });
        } catch (PaymentException $e) {
            // Gerçek sağlayıcıda kart reddi buraya düşer; kullanıcı formda görür
            throw ValidationException::withMessages(['raw_text' => 'Provizyon alınamadı: '.$e->getMessage()]);
        }

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
        ]);
    }

    public function cancel(Request $request, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        abort_if($order->customer_id !== $request->user()->id, 403);
        abort_unless(in_array($order->status, [OrderStatus::Reserved, OrderStatus::Assigned], true), 422);

        $courier = $order->courier; // iptal öncesi atanmış kurye (varsa haber verilecek)

        DB::transaction(function () use ($order, $gateway) {
            // Yarış koşuluna karşı satırı kilitle ve durumu yeniden doğrula:
            // eşzamanlı çift iptal → çift çözme denemesi önlenir.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, [OrderStatus::Reserved, OrderStatus::Assigned], true), 422);

            // Provizyonu hiç tahsil etmeden çöz — para zaten hesaba geçmemişti
            if ($auth = $locked->activeAuthorization()) {
                $gateway->void($auth);
            }

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
     * Ek ödeme: fiş provizyonu aştığında müşteri farkı onaylar.
     * Mevcut provizyon tamamen tahsil edilir, fark AYRI bir çekim olarak alınır —
     * gerçek sağlayıcıda da provizyondan fazlası tahsil edilemez.
     */
    public function payExtra(Request $request, Order $order, PaymentGateway $gateway): RedirectResponse
    {
        abort_if($order->customer_id !== $request->user()->id, 403);
        abort_unless($order->status === OrderStatus::RequiresExtraPayment, 422);

        $extra = (float) $order->extra_required_amount;
        $reserved = (float) $order->reserved_amount;

        try {
            DB::transaction(function () use ($order, $gateway, $extra, $reserved) {
                $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === OrderStatus::RequiresExtraPayment, 422);

                // 1) İlk provizyonun tamamını tahsil et
                $auth = $locked->activeAuthorization();
                abort_if($auth === null, 422);
                $gateway->capture($auth, $reserved);

                // 2) Farkı yeni bir provizyon açıp hemen tahsil et
                $gateway->capture($gateway->authorize($locked, $extra, 'Ek ödeme'), $extra);

                $locked->update([
                    'status' => OrderStatus::Delivered,
                    'captured_amount' => round($reserved + $extra, 2),
                    'refund_amount' => 0,
                    'delivered_at' => now(),
                ]);
            });
        } catch (PaymentException $e) {
            throw ValidationException::withMessages(['extra' => 'Ek ödeme alınamadı: '.$e->getMessage()]);
        }

        return redirect()->route('customer.orders.show', $order)->with(
            'success',
            $order->code.' · '.number_format($extra, 0, ',', '.').' TL ek ödeme alındı, sipariş tamamlandı.',
        );
    }

    /** Demo sipariş kodu (A24-119, A24-120, ...). */
    /**
     * Sipariş kodu satırın KENDİ id'sinden türer; benzersizliği veritabanı garanti eder.
     *
     * Eskiden 'A24-'.(max(id)+114) idi: kod tablonun en büyük id'sinden türediği için iki
     * eşzamanlı sipariş aynı kodu alabiliyordu (code unique → biri 500 yerdi) ve bir satır
     * silinince sayaç geriye gidip var olan bir kodla çakışıyordu. Yıl da elle gömülüydü.
     */
    private function codeFor(Order $order): string
    {
        return 'A'.now()->format('y').'-'.$order->id;
    }
}
