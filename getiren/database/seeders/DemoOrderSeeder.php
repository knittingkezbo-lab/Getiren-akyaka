<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Payments\PaymentGateway;
use Illuminate\Database\Seeder;

class DemoOrderSeeder extends Seeder
{
    public function run(PaymentGateway $gateway): void
    {
        // Idempotent: demo siparişleri zaten varsa çık
        if (Order::where('code', 'A24-116')->exists()) {
            return;
        }

        $gencer = User::where('email', 'gencer@bizsim.com')->first();
        $selin = User::where('email', 'selin@example.com')->first();
        $baris = User::where('email', 'baris@example.com')->first();
        $mert = User::where('email', 'mert@getiren.test')->first();
        $deniz = User::where('email', 'deniz@getiren.test')->first();

        $akyaka = Zone::where('key', 'akyaka')->first();
        $gokova = Zone::where('key', 'gokova')->first();
        $akcapinar = Zone::where('key', 'akcapinar')->first();

        // ---- Gencer #1: teslim edildi (tam döngü: provizyon → fişe göre tahsil → kalan çözüldü) ----
        $o1 = Order::create([
            'code' => 'A24-116', 'customer_id' => $gencer->id, 'courier_id' => $mert->id, 'zone_id' => $akyaka->id,
            'raw_text' => 'zeytinyağı, 2 ekmek, süt', 'address_label' => 'Ev', 'address_text' => 'Şirinyer Mah. Deniz Sk.',
            'items_total' => 430, 'safety_buffer' => 65, 'service_fee' => 250, 'reserved_amount' => 745,
            'status' => OrderStatus::Estimated,
        ]);
        $o1->items()->createMany([
            ['name' => 'Zeytinyağı', 'qty' => 1, 'estimated_price' => 220, 'actual_price' => 210],
            ['name' => 'Ekmek', 'qty' => 2, 'estimated_price' => 30, 'actual_price' => 32],
            ['name' => 'Süt', 'qty' => 1, 'estimated_price' => 50, 'actual_price' => 45],
        ]);
        // Fiş: ürün 372 + hizmet 250 = 622 tahsil; provizyonun kalan 123'ü sağlayıcıda çözülür
        $gateway->capture($gateway->authorize($o1, 745, 'Sipariş provizyonu'), 622);
        $o1->update([
            'status' => OrderStatus::Delivered, 'actual_receipt_amount' => 372, 'captured_amount' => 622,
            'refund_amount' => 123, 'reserved_at' => now()->subDays(2), 'delivered_at' => now()->subDays(2),
        ]);

        // ---- Gencer #2: aktif (alışverişte) — provizyon açık ----
        $o2 = Order::create([
            'code' => 'A24-118', 'customer_id' => $gencer->id, 'courier_id' => $mert->id, 'zone_id' => $akyaka->id,
            'raw_text' => '1 kutu süt, 2 ağrı kesici, ekmek', 'address_label' => 'Ev', 'address_text' => 'Şirinyer Mah. Deniz Sk.',
            'customer_note' => 'Zil çalışmıyor, arayın lütfen',
            'items_total' => 377, 'safety_buffer' => 57, 'service_fee' => 250, 'reserved_amount' => 684,
            'status' => OrderStatus::Shopping, 'reserved_at' => now(),
        ]);
        $o2->items()->createMany([
            ['name' => 'Süt', 'qty' => 1, 'estimated_price' => 50],
            ['name' => 'Ağrı kesici', 'qty' => 2, 'estimated_price' => 300],
            ['name' => 'Ekmek', 'qty' => 1, 'estimated_price' => 15],
        ]);
        $gateway->authorize($o2, 684, 'Sipariş provizyonu');

        // ---- Gencer #3: fiş provizyonu aştı (ek ödeme bekliyor) — provizyon hâlâ açık ----
        $o3 = Order::create([
            'code' => 'A24-115', 'customer_id' => $gencer->id, 'courier_id' => $deniz->id, 'zone_id' => $akyaka->id,
            'raw_text' => 'zeytinyağı, 2 ekmek', 'address_label' => 'Ev', 'address_text' => 'Şirinyer Mah. Deniz Sk.',
            'items_total' => 200, 'safety_buffer' => 30, 'service_fee' => 250, 'reserved_amount' => 480,
            'status' => OrderStatus::RequiresExtraPayment,
            'actual_receipt_amount' => 310, 'extra_required_amount' => 80,
            'reserved_at' => now()->subDay(),
        ]);
        $o3->items()->createMany([
            ['name' => 'Zeytinyağı', 'qty' => 1, 'estimated_price' => 170, 'actual_price' => 250],
            ['name' => 'Ekmek', 'qty' => 2, 'estimated_price' => 30, 'actual_price' => 60],
        ]);
        $gateway->authorize($o3, 480, 'Sipariş provizyonu');

        // ---- Selin: yolda ----
        $so = Order::create([
            'code' => 'A24-117', 'customer_id' => $selin->id, 'courier_id' => $deniz->id, 'zone_id' => $gokova->id,
            'raw_text' => 'kahve, 3 su, ekmek', 'address_label' => 'Ev', 'address_text' => 'Akçapınar yolu No:12',
            'items_total' => 228, 'safety_buffer' => 34, 'service_fee' => 350, 'reserved_amount' => 612,
            'status' => OrderStatus::OnTheWay, 'reserved_at' => now(),
        ]);
        $so->items()->createMany([
            ['name' => 'Kahve', 'qty' => 1, 'estimated_price' => 120],
            ['name' => 'Su', 'qty' => 3, 'estimated_price' => 105],
        ]);
        $gateway->authorize($so, 612, 'Sipariş provizyonu');

        // ---- Barış: provizyon alındı, kurye bekliyor ----
        $bo = Order::create([
            'code' => 'A24-114', 'customer_id' => $baris->id, 'zone_id' => $akcapinar->id,
            'raw_text' => 'gazete, yumurta, ekmek', 'address_label' => 'Ev', 'address_text' => 'Ataköy Cd. No:5',
            'items_total' => 200, 'safety_buffer' => 30, 'service_fee' => 350, 'reserved_amount' => 580,
            'status' => OrderStatus::Reserved, 'reserved_at' => now(),
        ]);
        $bo->items()->createMany([
            ['name' => 'Gazete', 'qty' => 1, 'estimated_price' => 30],
            ['name' => 'Yumurta', 'qty' => 1, 'estimated_price' => 140],
            ['name' => 'Ekmek', 'qty' => 1, 'estimated_price' => 30],
        ]);
        $gateway->authorize($bo, 580, 'Sipariş provizyonu');
    }
}
