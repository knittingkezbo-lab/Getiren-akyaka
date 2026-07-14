# Getiren · Akyaka — Laravel 13 + Vue (Inertia) · Docker

Yerel concierge/kurye uygulaması. Müşteri serbest metinle sipariş verir; sistem tutarı
tahmin edip ödeme aracında **provizyona alır**, kurye alışverişi yapıp fişi girer, sistem
**gerçek fişe göre keser, fazlasını geri bırakır** (yetmezse ek ödeme ister).

**Getiren para tutmaz.** Sanal cüzdan / kullanıcı bakiyesi yoktur (hukuki tercih); ödeme her
sipariş için ayrı bir provizyon olarak açılır ve `payment_authorizations` tablosunda izlenir.

**Yığın:** Laravel 13 · Vue 3 + Inertia · MySQL 8 · Laravel Reverb (WebSocket) · Docker
**Roller:** Müşteri · Kurye · Yönetici — ayrı alanlar, kendi yazdığımız session auth.

## Öne çıkan özellikler

- **Provizyon (authorize → capture → void)** — para hareketi yalnızca `PaymentGateway` arayüzünden
  geçer. Bugün `DemoGateway` takılı (gerçek para hareketi yok, durum makinesi gerçek PSP ile aynı);
  sağlayıcı anahtarları gelince `config/payments.php`'ye yeni sürücü eklenir, **uygulama kodu değişmez**.
  Kısmi tahsilde provizyonun kalanı serbest kalır — "fazlasını iade et" budur. Kapanmış provizyona
  ikinci kez dokunulamaz: çift-tahsil/çift-iade bu katmanda imkânsız.
- **Sipariş akışı** — serbest-metin tahmini (yazarken öneri + gerçek fişten sözlük öğrenme),
  kurye üstlenme → alışveriş → yolda → fiş, ek-ödeme tamamlama, iptal + iade (çift-iptal kilidi).
- **Bildirimler** — uygulama-içi zil + e-posta + **canlı WebSocket**. Kanal (e-posta /
  uygulama) ve olay bazlı tercihler; hem müşteri hem kurye kendi tercihlerini yönetir.
- **Canlı güncelleme** — zil ve sipariş takibi sayfa yenilenmeden güncellenir (Reverb + Echo).
- **Kayıt güvenliği** — e-posta doğrulama (toggle'lı `MustVerifyEmail`) + **kurye admin onayı**:
  kurye kaydı "onay bekliyor" başlar, admin onaylayana kadar iş alamaz.
- **Hukuki sertleştirme** — `/hukuki/*` sayfaları (taslak), sipariş onay checkbox'ı +
  `terms_version` kaydı, yasaklı ürün uyarısı, "ürün satıcısı değil" konumlandırması.
- **Denetim kaydı** — `/yonetici/denetim`: kurye onay/red, sipariş atama ve ayar değişiklikleri
  kim–ne–ne zaman–hangi IP olarak yazılır; ayarlarda **eski → yeni** farkı tutulur. Kayıt
  değiştirilemez/silinemez (model seviyesinde), hedef silinse bile adı kopyalandığı için okunur kalır.

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
| phpMyAdmin | http://localhost:8082  (sunucu `db`, `getiren` / `secret`) |
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
  E-posta doğrulama `AUTH_EMAIL_VERIFICATION` ile açılır (`MustVerifyEmail` + imzalı rota +
  `verified` middleware); kapalıyken kayıt anında doğrulanır. Kurye kaydı admin onayı bekler
  (`courier.approved` middleware). Oturum hareketsizlik zaman aşımı: `SESSION_LIFETIME` (30 dk)
  + istemci idle-logout (`useIdleLogout`). Proxy/tünel arkası için `trustProxies`.
- **Ödeme** — `App\Payments\PaymentGateway`: `authorize()` → `capture()` / `void()`. Sürücü
  `config/payments.php`'den seçilir (`PAYMENT_DRIVER`, varsayılan `demo`), container'da bağlanır.
  Ek ödeme = ilk provizyonu tam kes + **farkı ayrı bir provizyonla** çek (gerçek PSP'de de
  provizyondan fazlası tahsil edilemez). Değişmez: _kesilen + geri bırakılan = provizyona alınan_
  (`TestCase::assertAuthorizationsConsistent`).
- **Bildirim** — `OrderNotification.via()` istenen kanalları alıcının **olay** + **kanal**
  tercihleriyle kesiştirir; web bildirimi giderken `broadcast` kanalını da ekler (canlı push).
- **Canlı** — Reverb standalone (Redis yok). App → `reverb:8080` (iç ağ), tarayıcı →
  `localhost:8085`. `AppLayout` kullanıcının özel kanalına abone → bildirimde `router.reload()`.
  `QUEUE_CONNECTION=sync` (worker'sız senkron broadcast).
- Enum'lar: `App\Enums\{UserRole, OrderStatus, AuthorizationStatus, AuditAction}`.

## Veri modeli (migration'lar)

- **users** — `role` (customer/courier/admin), `phone`, `iban`/`iban_holder` (iade/çekim),
  `email_verified_at`, `approved_at` (kurye onayı), `notify_email`, `notify_web`,
  `notification_events` (JSON: olay tercih haritası)
- **payment_authorizations** — sipariş başına provizyon (authorized/captured/voided/failed),
  sağlayıcı + referans, alınan/kesilen tutar. Bir siparişin birden çok provizyonu olabilir (ek ödeme).
  _(`wallets` + `wallet_transactions` kaldırıldı — migration 000017.)_
- **orders** + **order_items** · **addresses** · **notifications** (database kanalı)
- **zones** — Akyaka 250 · Gökova 350 · Akçapınar 350 (+ Ataköy pasif)
- **price_hints** — serbest-metin tahmin sözlüğü · **settings** — %güvenlik payı vb.
- **audit_logs** — yönetici eylemleri (append-only). Aktör/hedef adları satır silinse de
  okunur kalsın diye kopyalanır; `meta` JSON'unda ayarların eski→yeni farkı durur.

## Test & CI

```bash
docker compose exec app php artisan test    # 75 test / 255 assertion (sqlite :memory)
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

## Paylaşım (uzaktan demo)

Geliştirmede asset'leri Vite (`localhost:5173`) servis eder — uzaktan biri erişecekse önce
production build alıp Vite'ı durdur, sonra tünelle:

```bash
docker compose run --rm node npm run build      # asset'leri derle
docker compose stop node                         # Vite'ı durdur → build modu
cloudflared tunnel --url http://localhost:8080   # geçici public URL (trycloudflare.com)
```

`APP_DEBUG=false` yap (herkese açıkken); Cloudflare https'i için `trustProxies` zaten ekli.
Geliştirmeye dönmek: `docker compose start node`. WebSocket canlı güncelleme tek tünelde
ek ayar (nginx → Reverb proxy) ister.

## Sırada

Gerçek ödeme entegrasyonu (iyzico / PayTR / Stripe sandbox) — redirect + webhook akışı.
