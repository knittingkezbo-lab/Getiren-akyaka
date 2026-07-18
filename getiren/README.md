# Getiren · Akyaka — Laravel 13 + Vue (Inertia) · Docker

Yerel concierge/kurye uygulaması. Müşteri **serbest metinle** ne istediğini yazar; sistem tutarı
tahmin edip ödeme aracında **provizyona alır**, kurye alışverişi yapıp gerçek fiyatları girer, sistem
**gerçek fişe göre keser, fazlasını geri bırakır** (yetmezse ek ödeme ister).

> **Getiren para tutmaz.** Sanal cüzdan / kullanıcı bakiyesi **yoktur** (hukuki tercih). Ödeme her
> sipariş için ayrı bir **provizyon** olarak açılır ve `payment_authorizations` tablosunda izlenir.
> Getiren **ürün satıcısı değildir** — ürünleri müşteri adına temin eden aracıdır.

**Yığın:** Laravel 13 · PHP 8.4 · Vue 3 + Inertia · MySQL 8 · Vite · Laravel Reverb (WebSocket) · Docker
**Roller:** Müşteri · Kurye · Yönetici — ayrı alanlar, kendi yazdığımız session auth.
**Bölgeler:** Akyaka · Gökova · Akçapınar (Muğla)

---

## Öne çıkan özellikler

- **Provizyon (authorize → capture → void)** — para hareketi yalnızca `PaymentGateway` arayüzünden
  geçer. Bugün `DemoGateway` takılı: **gerçek para hareketi yok**, yalnızca durum makinesini modeller.
  Kısmi tahsilde provizyonun kalanı serbest kalır → "fazlasını iade et" budur. `DemoGateway`'de
  kapanmış provizyon ikinci kez capture/void edilemez (çift-tahsil koruması **bu sürücü için**
  test edilmiştir; gerçek PSP'de aynı garanti idempotency + reconciliation ister — bkz. Bilinen açıklar).
- **Tahmin motoru** — serbest metin → katmanlı fiyat sözlüğü → provizyon. Gerçek fiyatlardan
  kendi kendine öğrenir (aşağıda ayrı bölüm).
- **Sipariş akışı** — yazarken öneri, kurye üstlenme → alışveriş → yolda → fiş girişi,
  ek-ödeme tamamlama, iptal (çift-iptal kilidi).
- **Bildirimler** — uygulama-içi zil + e-posta + (opsiyonel) canlı WebSocket. Kanal ve **olay bazlı**
  tercihler; hem müşteri hem kurye kendi tercihini yönetir.
- **Canlı güncelleme (opsiyonel)** — `LIVE_UPDATES` bayrağı. Kapalıyken Reverb'siz çalışır
  (bildirim yine DB + e-postaya gider, tarayıcı WebSocket'e bağlanmaya çalışmaz).
- **Kayıt güvenliği** — e-posta doğrulama (toggle'lı `MustVerifyEmail`) + **kurye admin onayı**.
- **Hukuki + şirket bilgisi** — 8 hukuki sayfa, şirket kimliği tek merkezden, panelden düzenlenebilir.
- **Denetim kaydı** — yönetici eylemleri değiştirilemez şekilde kayıt altında.

---

## Tahmin motoru (kalbi burası)

**Felsefe:** tahminin işi *isabet* değil **yeterlilik**. Fazla tahmin zararsızdır (fark müşteriye
çözülür); az tahmin müşteriye **"ek ödeme" sürtünmesi** yaşatır. Bu yüzden bilinçli olarak yukarı sapılır.

**Akış:** serbest metin → virgülle böl → baştaki sayı = adet (`2 ekmek` → 2) → kelimeyi sözlükle
eşleştir (uzun anahtar önce) → adet × birim fiyat → `min_order_total` tabanı → **güvenlik payı** →
**bölge hizmet bedeli** = provizyon tutarı. Hesap **sunucuda otoriter** (istemci sadece önizleme yapar).

### Katmanlı fiyat kaynağı (`App\Enums\PriceSource`)

Güven sırası — **gerçek yerel fiyat her zaman kazanır**:

| Katman | Nereden | Not |
|---|---|---|
| `observed` | **Kuryenin girdiği gerçek fiyat** (fiş ya da elle giriş) | En güvenilir — Akyaka'da fiilen ödenen para |
| `manual` | Admin'in panelden girdiği / toplu içe aktardığı | Soğuk başlangıç |
| `reference` | Dış kaynak (iç kullanım, müşteriye gösterilmez) | Şu an kullanılmıyor |
| `fallback` | Hiç bilgi yok → `fallback_item_price` | + pay yükseltilir |

### Kendi kendine öğrenme

Kurye fiş ekranında gerçek fiyatları girer (**fiş fotoğrafı/OCR yok — elle giriş normal akış**).
`JobController::learnItemPrices()` → `PriceHint::recordObservation()`:

- **İlk gerçek gözlem tahmini DOĞRUDAN ezer** → sezonluk iş için hızlı yakınsama.
- Sonraki gözlemler **üstel ortalamayla** yumuşatılır (α = 0.4) → tek bir uç fiyat (kampanya, hatalı
  giriş) sözlüğü bozmasın.
- Bilinmeyen kalem sözlüğe **eklenir**, bilinen kalemin fiyatı **tazelenir** (yoksa sözlük enflasyonun
  gerisinde kalır).
- Birim fiyat öğrenilir (satır toplamı ÷ adet).

### Bilinmeyen kalem

Sözlükte olmayan kalem → `fallback_item_price` (varsayılan 60 TL) **ve** güvenlik payı
`safety_buffer_pct` (%15) yerine `unknown_buffer_pct` (%35) olur. Böylece ek-ödeme sürtünmesi azalır.

> **Yemek siparişi ekstra kod istemez:** "Ayışığı'ndan 2 adana" → sözlük tanımaz → %35 pay →
> kurye gerçek fiyatı girer → öğrenilir. Restoran menüsü modellemeye gerek yok.

### Ölç ve ayarla

Admin > Ayarlar > **Tahmin isabeti**: son 50 kapanan siparişte tahmini ürün tutarı vs gerçek fiş →
**ortalama sapma** + **ek ödeme oranı**. Payı tahminle değil **veriyle** ayarlarsın (ek ödeme oranı
yükseliyorsa payı artır).

### Sözlüğü besleme

- **Toplu içe aktarma** (Admin > Ayarlar): satır başına `kelime; kategori; fiyat`. Türkçe ondalık ve
  `TL` eki ayrıştırılır. **Gözlenen gerçek fiyatları EZMEZ.**
- **Gözden geçirme:** sözlükte kaynak rozeti + "gözden geçir" işareti + filtre
  (`PriceHint::needsReview()` — hiç gözlenmemiş ya da 30 günden eski).
- Fiyat bakmak için resmi kaynak: **[marketfiyati.org.tr](https://marketfiyati.org.tr)** (konum bazlı,
  A101/BİM/ŞOK/Migros dahil). ⚠️ Sitenin sorumluluk reddi **kopyalamayı yasaklıyor** → otomatik
  içe aktarıcı yazılmadı; elle bakıp girmek platformun amacına uygun.

---

## Kurulum (Docker)

```bash
cd getiren
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
```

| Servis | Adres |
|---|---|
| Uygulama (nginx → php-fpm) | http://localhost:8080 |
| phpMyAdmin | http://localhost:8082  (sunucu `db`, `getiren` / `secret`) |
| Mailpit (e-posta yakalayıcı) | http://localhost:8025 |
| Vite dev (HMR) | http://localhost:5173 |
| Reverb (WebSocket) | ws://localhost:8085 |
| MySQL (dışarıdan) | `127.0.0.1:33061` · db `getiren` · `getiren` / `secret` |

### Demo hesaplar (şifre hepsinde `password`)

Yalnızca **dev** ortamında seed edilir (`APP_ENV != production`).

| Rol | E-posta |
|---|---|
| Yönetici | `admin@getiren.test` |
| Kurye | `mert@getiren.test` (+4) |
| Müşteri | `gencer@bizsim.com` (+4) |

---

## Mimari notları

- **Auth** — session (`Auth::attempt`), `EnsureUserHasRole` (`role:` alias), rol → açılış rotası.
  E-posta doğrulama `AUTH_EMAIL_VERIFICATION` ile açılır; kapalıyken kayıt anında doğrulanır.
  Kurye kaydı admin onayı bekler (`courier.approved`). Oturum zaman aşımı `SESSION_LIFETIME` (30 dk)
  + istemci idle-logout (`useIdleLogout`). Proxy/tünel arkası için `trustProxies`.
- **Ödeme** — `App\Payments\PaymentGateway`: `authorize()` → `capture()` / `void()`. Sürücü
  `config/payments.php`'den (`PAYMENT_DRIVER`). Ek ödeme = ilk provizyonu tam kes + **farkı ayrı
  provizyonla** çek (gerçek PSP'de de provizyondan fazlası tahsil edilemez).
  **Değişmez:** _kesilen + geri bırakılan = provizyona alınan_ (`TestCase::assertAuthorizationsConsistent`).
- **PayTR (İSKELET — TAMAMLANMADI)** — `PayTRGateway`'in `authorize/capture/void` metotları
  gerçek HTTP çağrısı **yapmaz**, bilinçli olarak exception atar. `PAYMENT_DRIVER=paytr` yazmak
  şu an **hiçbir siparişin oluşmamasına** yol açar. Callback (`/odeme/paytr/geri-bildirim`)
  `PAYTR_CALLBACK_ENABLED` bayrağının arkasında ve **varsayılan kapalı (404)** — imza doğrulaması
  henüz bağlanmadı. Üretimde `demo` sürücüsü de reddedilir. Yani **bu uygulama şu an gerçek
  ödemeyle canlıya alınamaz.** Canlıya almadan önce: **PayTR'de "Ön Provizyon" yetkisi şart** —
  standart hesap yalnızca anında tahsilat verir. Kart bilgisi **bize hiç gelmez** (PCI yükü PayTR'de);
  müşteri PayTR sayfasında girer, sonuç callback ile döner (`pending` → `authorized`). Bu yüzden
  go-live'da sipariş akışına **yönlendirme adımı** eklenmeli.
- **Şirket bilgisi** — `App\Support\Company`: DB'de kayıt varsa (boş string dahil) o, yoksa
  `config/company.php` (env `COMPANY_*`). Admin panelden düzenler; bir alanı **boşaltmak = gizlemek**.
  Hassas veri (vergi no/adres) repoda değil, `.env`'de.
- **Hukuki** — `LegalController`, 8 sayfa: kullanım şartları, **mesafeli sözleşme + ön bilgilendirme**,
  teslimat, iptal/iade, yasaklı ürünler, KVKK, gizlilik/çerez, iletişim. Şirket bilgisi enjekte edilir.
  `LEGAL_DRAFT=true` iken "TASLAK" bandı görünür (avukat onaylayınca panelden kapatılır).
- **Bildirim** — `OrderNotification::via()` istenen kanalları alıcının **olay** + **kanal** tercihleriyle
  kesiştirir; `LIVE_UPDATES` açıksa `broadcast` kanalını da ekler.
- **Canlı** — Reverb standalone (Redis yok). `LIVE_UPDATES=false` iken `echo.js` Echo'yu **hiç başlatmaz**
  (konsol temiz). `QUEUE_CONNECTION=sync` (worker'sız).
- **Denetim** — `AuditLog` append-only (model seviyesinde update/delete engelli). Eylem ve kayıt aynı
  transaction içinde → "oldu ama loglanmadı" imkânsız. Hedef silinse bile adı kopyalandığı için okunur.
- Enum'lar: `App\Enums\{UserRole, OrderStatus, AuthorizationStatus, AuditAction, PriceSource}`.

---

## Veri modeli (migration'lar)

- **users** — `role` (customer/courier/admin), `phone`, `iban`/`iban_holder`, `email_verified_at`,
  `approved_at` (kurye onayı), `notify_email`, `notify_web`, `notification_events` (JSON)
- **orders** + **order_items** — `items_total`, `safety_buffer`, `service_fee`, `reserved_amount`,
  `actual_receipt_amount`, `captured_amount`, `refund_amount`, `extra_required_amount`, `terms_version`
- **payment_authorizations** — sipariş başına provizyon (`pending`/`authorized`/`captured`/`voided`/`failed`),
  sağlayıcı + referans, alınan/kesilen tutar. Bir siparişin **birden çok** provizyonu olabilir (ek ödeme).
- **price_hints** — tahmin sözlüğü + `source`, `observed_count`, `last_observed_at`, `reference_price`
- **settings** — `safety_buffer_pct`, `unknown_buffer_pct`, `fallback_item_price`, `min_order_total`,
  `accepting_orders` (kapalıyken tahmin de sipariş de reddedilir) + `company_*` override'ları
- **audit_logs** — yönetici eylemleri (append-only); `meta`'da ayarların eski→yeni farkı
- **zones** · **addresses** · **notifications**
- _(`wallets` + `wallet_transactions` kaldırıldı — migration 000017; 000018 eski siparişlere provizyon üretti.)_

---

## Test & CI

```bash
docker compose exec app php artisan test                        # 142 test (sqlite :memory)
docker compose exec app php artisan test -c phpunit.mysql.xml   # aynı takım, gerçek MySQL 8
```

**Neden iki kere koşuyor:** sqlite hızlıdır ama `lockForUpdate`, koşullu `UPDATE` ve
unique çakışmasını MySQL gibi modellemez — yani paranın etrafındaki yarış davranışları
sqlite'ta "geçmiş" görünebilir. `phpunit.mysql.xml` aynı takımı gerçek motorda koşar.

> `phpunit.mysql.xml` içindeki `DB_DATABASE` **`force="true"` ile `getiren_test`'e sabitlenmiştir.**
> `RefreshDatabase` bağlandığı şemadaki tüm tabloları düşürür; bu kilit olmasa tek bir
> `artisan test` geliştirme veritabanını silebilirdi. `TestDatabaseSafetyTest` bu kapıyı bekler.

GitHub Actions (`.github/workflows/ci.yml`) 4 job:

| Job | Ne yapar |
|---|---|
| `tests` | Tüm takım, sqlite :memory: — hızlı geri bildirim |
| `mysql-tests` | Aynı takım, MySQL 8 servisinde — kilit/yarış davranışı gerçek motorda |
| `assets` | `npm run build` (vite) |
| `quality` | Pint (kod stili) + `composer audit` + `npm audit --audit-level=high` |

### Paranın etrafındaki kapılar

Bu testler bir kez gerçekten açık olan delikleri tutuyor; hepsi önce **kırık** yazıldı:

| Test | Tuttuğu delik |
|---|---|
| `OrderEstimateEndpointTest` | Ekranda gösterilen tutar ile provizyona alınan tutarın aynı olması (istemcide ayrı hesap yok) + fiyat sözlüğünün istemciye sızmaması |
| `OrderIntakeGateTest` | "Siparişleri kabul et" kapalıyken tahmin de sipariş de reddedilir |
| `CourierSettlePayloadTest` | Fiş siparişin kalem kümesiyle birebir eşleşir: tekrar eden kalem fişi şişiremez, eksik/yabancı kalem sessizce atlanamaz |
| `JobClaimRaceTest` | İki kurye aynı işi üstlenemez (koşul UPDATE'in içinde) |
| `AdminAssignGuardTest` | Onaysız kuryeye ve kapanmış siparişe atama yapılamaz |
| `OrderAddressTest` | Adressiz sipariş oluşmaz |
| `OrderCodeTest` | Sipariş kodu satırın kendi id'sinden türer (çakışma yok, yıl elle gömülü değil) |
| `LoginThrottleTest` | Giriş denemesi e-posta+IP başına sınırlı |
| `ProductionPaymentGuardTest` | Üretimde demo ödeme sürücüsü reddedilir |
| `PaymentCallbackDisabledTest` | Doğrulanmamış PayTR callback'i kapalı (404) |
| `IbanProtectionTest` | IBAN mod-97 checksum'dan geçer (tek hane hatası yakalanır) ve veritabanında şifreli durur |
| `TestDatabaseSafetyTest` | Testler asla gerçek şemaya bağlanamaz (`RefreshDatabase` veriyi silerdi) |

### Finansal veri koruması

- **IBAN mod-97:** Biçim kontrolü (`TR` + 24 rakam) tek başına yetmez — tek hanesi yanlış
  yazılmış IBAN de o kalıba uyar. `App\Rules\Iban` ISO 7064 checksum'ı doğrular, böylece
  para var olmayan/yanlış hesaba gitmez.
- **Şifreli saklama:** `iban` ve `iban_holder` `encrypted` cast ile saklanır (sütunlar bu
  yüzden `text`; şifreli değer ~230 karakter). **APP_KEY kaybedilirse geri alınamaz — yedekle.**
- **Maskeleme:** `$user->masked_iban` → `TR33 •••• 1326`. Kullanıcının kendi düzenleme
  formunda tam IBAN görünür (yoksa doğrulayamaz); maskeli hâl yönetici/hakediş ekranları içindir.
- **Güvenilir proxy:** `config/security.php`. `'*'` **kullanılmaz** — o, istemcinin kendi
  IP'sini uydurmasına izin verir ve e-posta+IP giriş limitini anlamsızlaştırırdı. Üretimde
  `TRUSTED_PROXIES` ile gerçek proxy/Cloudflare CIDR listesine daralt.

---

## Sık kullanılan komutlar

```bash
docker compose exec app php artisan migrate:fresh --seed   # şemayı sıfırla + seed
docker compose exec node npm run build                     # üretim asset'leri (mevcut konteynerde)
docker compose exec app php artisan tinker                 # REPL
docker compose logs -f app                                 # loglar (veya: reverb / node / mailpit)
docker compose down                                        # durdur (veri kalır)
docker compose down -v                                     # + veritabanını sil
```

> **Not (Vite):** Yeni bir Inertia sayfası eklendiğinde `app.js`'teki `import.meta.glob` bayat kalabilir —
> belirtisi **konsolda hatasız bembeyaz sayfa**. `vite.config.js`'teki `getiren:refresh-pages-glob`
> eklentisi bunu otomatik çözer (dosya ekleme/silmede `app.js` modülünü geçersizleştirir).

---

## Üretime çıkış

1. **`.env.production.example`** → sunucuda `.env`: `APP_ENV=production`, `APP_DEBUG=false`,
   `AUTH_EMAIL_VERIFICATION=true`, güvenli çerez, gerçek domain/SMTP, `COMPANY_*`, `PAYTR_*`.
2. **Seed** — `DatabaseSeeder` ortam-bilinçli: üretimde **yalnızca** `AdminSeeder`
   (env'den `ADMIN_EMAIL`/`ADMIN_PASSWORD`); demo veri (UserSeeder/DemoOrderSeeder) **asla** girmez
   (savunmacı guard'lar var). Zone/Setting/PriceHint her ortamda seed edilir.
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=AdminSeeder
   ```
3. **İnceleme hesabı** (PayTR vb. için, opsiyonel/geçici):
   ```bash
   php artisan db:seed --class=ReviewSeeder   # REVIEW_EMAIL / REVIEW_PASSWORD env'den
   ```
4. **Reverb'siz başlangıç** — `LIVE_UPDATES=false`, `VITE_LIVE_UPDATES=false`,
   `BROADCAST_CONNECTION=null`. Talep gelince: bayrakları `true` yap + `REVERB_*` doldur +
   reverb servisini çalıştır + asset build.
5. **Fiyat sözlüğü** — Admin > Ayarlar > Toplu içe aktarma ile gerçek yerel fiyatları yükle.
   Sonrasında sistem fişlerden öğrenmeye devam eder.

### Bekleyenler (dış bağımlılık)

- **PayTR** anahtarları + **Ön Provizyon yetkisi** → gerçek ödeme akışı + yönlendirme adımı
- **Avukat** — hukuki metinlerin kesinleşmesi (özellikle mesafeli sözleşmede **cayma hakkı**),
  reçetesiz ilaç ve yemek taşımanın teyidi → sonra `LEGAL_DRAFT` kapatılır
- **ETBİS** kaydı (site yayına girince) → `COMPANY_ETBIS`
- Barındırma + SSL + gerçek SMTP; IBAN'a çekim akışı

---

## Paylaşım (uzaktan demo)

Geliştirmede asset'leri Vite servis eder; uzaktan biri erişecekse önce build alıp Vite'ı durdur:

```bash
docker compose exec node npm run build
docker compose stop node                         # Vite'ı durdur → build modu
cloudflared tunnel --url http://localhost:8080   # geçici public URL
```

`APP_DEBUG=false` yap. Geliştirmeye dönmek: `docker compose start node`.
