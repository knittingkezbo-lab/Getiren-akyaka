# Getiren · Akyaka — Laravel 13 + Vue (Inertia) · Docker

Yerel concierge/kurye uygulaması. Müşteri serbest metinle sipariş verir; sistem tutarı
tahmin edip cüzdandan **bloke eder**, kurye alışverişi yapıp fişi girer, sistem **gerçek
fişe göre keser, fazlasını iade eder** (yetmezse ek ödeme ister). Her para hareketi
`wallet_transactions` (ledger) tablosuna yazılır.

**Yığın:** Laravel 13 · Vue 3 + Inertia · MySQL 8 · Laravel Reverb (WebSocket) · Docker
**Roller:** Müşteri · Kurye · Yönetici — ayrı alanlar, kendi yazdığımız session auth.

## Öne çıkan özellikler

- **Sanal cüzdan + ledger** — bloke → fişe göre kes → fark iade; 6 işlem türü, tek geçiş
  noktası (`Wallet::recordTransaction`), değişmez defter (`balance == Σamount`).
- **Sipariş akışı** — serbest-metin tahmini, kurye üstlenme → alışveriş → yolda → fiş,
  ek-ödeme tamamlama, iptal + iade (çift-iptal kilidi ile).
- **Bildirimler** — uygulama-içi zil + e-posta + **canlı WebSocket**. Kanal (e-posta /
  uygulama) ve olay bazlı tercihler; hem müşteri hem kurye kendi tercihlerini yönetir.
- **Canlı güncelleme** — zil ve sipariş takibi sayfa yenilenmeden güncellenir (Reverb + Echo).

## Kurulum (Docker)

```bash
cd getiren
docker compose up -d        # tüm servisler
docker compose exec app php artisan migrate:fresh --seed
```

> Sıfırdan klonda önce: `cp .env.example .env && docker compose exec app php artisan key:generate`
> Canlı bildirim için `.env`'e `REVERB_APP_ID/KEY/SECRET` girilmeli (bu makinede hazır).

| Servis | Adres |
|---|---|
| Uygulama (nginx → php-fpm) | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081  (sunucu `db`, `getiren` / `secret`) |
| Mailpit (e-posta yakalayıcı) | http://localhost:8025 |
| Vite dev (HMR) | http://localhost:5173 |
| Reverb (WebSocket) | ws://localhost:8085 |
| MySQL (dışarıdan) | `127.0.0.1:33061`  db `getiren` · `getiren` / `secret` |

## Demo hesaplar (şifre hepsinde `password`)

| Rol | E-posta |
|---|---|
| Yönetici | `admin@getiren.test` |
| Kurye | `mert@getiren.test` (+4 kurye) |
| Müşteri | `gencer@bizsim.com` (+4 müşteri) |

## Mimari notları

- **Auth** — session (`Auth::attempt`), `EnsureUserHasRole` (`role:` alias), rol → açılış rotası.
- **Ledger** — `Wallet::recordTransaction(type, amount, reservedDelta, order, note, meta)`
  tek giriş noktası; bakiye/bloke önbelleğini günceller, değişmez satır yazar.
- **Bildirim** — `OrderNotification.via()` istenen kanalları alıcının **olay** + **kanal**
  tercihleriyle kesiştirir; web bildirimi giderken `broadcast` kanalını da ekler (canlı push).
- **Canlı** — Reverb standalone (Redis yok). App → `reverb:8080` (iç ağ), tarayıcı →
  `localhost:8085`. `AppLayout` kullanıcının özel kanalına abone → bildirimde `router.reload()`.
  `QUEUE_CONNECTION=sync` (worker'sız senkron broadcast).
- Enum'lar: `App\Enums\{UserRole, OrderStatus, TransactionType}`.

## Veri modeli (migration'lar)

- **users** — `role` (customer/courier/admin), `phone`, `notify_email`, `notify_web`,
  `notification_events` (JSON: olay bazlı tercih haritası)
- **wallets** + **wallet_transactions** — cüzdan + ledger (topup/hold/capture/refund/extra_charge/release)
- **orders** + **order_items** · **addresses** · **notifications** (database kanalı)
- **zones** — Akyaka 250 · Gökova 350 · Akçapınar 350 (+ Ataköy pasif)
- **price_hints** — serbest-metin tahmin sözlüğü · **settings** — %güvenlik payı vb.

## Test & CI

```bash
docker compose exec app php artisan test    # 45 test / 161 assertion (sqlite :memory)
```

GitHub Actions (`.github/workflows/ci.yml`) her push'ta iki job koşar:
`tests` (`php artisan test`) ve `assets` (`npm run build`).

## Sık kullanılan komutlar

```bash
docker compose exec app php artisan migrate:fresh --seed   # şemayı sıfırla + seed
docker compose exec app php artisan tinker                  # REPL
docker compose logs -f app                                  # loglar (veya: reverb / node / mailpit)
docker compose down                                         # durdur (veri kalır)
docker compose down -v                                      # + veritabanını sil
```

## Sırada

Gerçek ödeme entegrasyonu (iyzico / PayTR / Stripe sandbox) — redirect + webhook akışı.
