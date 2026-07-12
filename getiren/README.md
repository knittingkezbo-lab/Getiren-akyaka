# Getiren · Akyaka — Laravel + Vue (Inertia) · Docker

Yerel concierge/kurye uygulamasının backend'i. **Laravel 13 · MySQL 8 · Docker.**
Çekirdek mantık: sipariş tutarını **bloke et → gerçek fişe göre kes → fazlasını iade et**;
her para hareketi `wallet_transactions` (ledger) tablosuna yazılır.

## Kurulum (Docker)

```bash
cd getiren
docker compose up -d        # app · web(nginx) · db(mysql) · phpmyadmin
docker compose exec app php artisan migrate:fresh --seed
```

> `.env` bu makinede hazır (APP_KEY dolu). Sıfırdan klonlarsanız önce:
> `cp .env.example .env && docker compose exec app php artisan key:generate`

| Servis | Adres |
|---|---|
| Uygulama (nginx → php-fpm) | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081  (sunucu: `db`, kullanıcı: `getiren` / `secret`) |
| MySQL (dışarıdan) | `127.0.0.1:33061`  db: `getiren` · `getiren` / `secret` |

## Demo hesaplar (şifre hepsinde `password`)

| Rol | E-posta |
|---|---|
| Yönetici | `admin@getiren.test` |
| Kurye | `mert@getiren.test` (+4 kurye) |
| Müşteri | `gencer@bizsim.com` (+4 müşteri) |

## Veri modeli (migration'lar)

- **users** — `role` (customer/courier/admin), `phone`
- **wallets** + **wallet_transactions** — cüzdan + ledger (topup/hold/capture/refund/extra_charge)
- **zones** — Akyaka 250 · Gökova 350 · Akçapınar 350 (+ Ataköy pasif)
- **price_hints** — serbest-metin tahmin sözlüğü · **settings** — %güvenlik payı vb.
- **orders** + **order_items** · **addresses**

Enum'lar: `App\Enums\{UserRole, OrderStatus, TransactionType}`.
Ledger tek geçiş noktası: `Wallet::recordTransaction()` (bakiye/bloke önbelleğini de günceller).

## Sık kullanılan komutlar

```bash
docker compose exec app php artisan migrate:fresh --seed   # şemayı sıfırla + seed
docker compose exec app php artisan tinker                  # REPL
docker compose exec app composer install                   # bağımlılıklar
docker compose logs -f app                                  # loglar
docker compose down                                         # durdur (veri kalır)
docker compose down -v                                      # + veritabanını sil
```

## Sırada
Kimlik doğrulama (kendi yazacağımız) + Inertia/Vue kurulumu ve Version 3 temasının bileşenlere taşınması.
